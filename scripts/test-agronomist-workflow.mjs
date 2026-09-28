import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const workflow = JSON.parse(fs.readFileSync(new URL('../n8n-workflows/agronomist-v2.json', import.meta.url), 'utf8'));
const nodes = new Map(workflow.nodes.map(node => [node.name, node]));
const node = name => {
  assert.ok(nodes.has(name), `Missing workflow node: ${name}`);
  return nodes.get(name);
};

// Evaluate actual saved expressions without pretending to execute n8n nodes or
// calling paid/external services. Native runtime behavior needs a live test.
const now = '2026-09-28T10:15:00.000Z';
function evaluate(value, {json = {}, body, prepared} = {}) {
  if (typeof value !== 'string' || !value.startsWith('={{')) return value;
  assert.ok(value.endsWith('}}'), 'Malformed n8n expression');
  return vm.runInNewContext(`(${value.slice(3, -2)})`, {
    $json: json,
    $: name => ({first: () => {
      if (name === 'Webhook') return {json: {body}};
      if (name === 'Prepare Context') return {json: prepared};
      throw new Error(`Unmocked linked node: ${name}`);
    }}),
    $now: {toUTC: () => ({toISO: () => now})},
  }, {timeout: 1000});
}

const fixture = () => ({
  schema_version: 2, user_id: 7, sessionId: 31,
  message: 'Когда собирать пшеницу?', image: null, mediaType: 'image/jpeg',
  context: {
    location: {scope: 'selected_point', point: {lat: 53.2, lon: 63.6}},
    field: {crop: 'пшеница', growth_stage: 'восковая спелость'},
    weather: {source: 'Old provider', status: 'available', data: 'STALE_WEATHER_MARKER'},
  },
  history: [{role: 'user', text: 'Ранее обсуждали поле'}, {role: 'assistant', text: 'Нужны измерения влажности'}],
});
const condition = (name, json) => {
  const entries = node(name).parameters.conditions.conditions;
  assert.equal(entries.length, 1);
  assert.equal(entries[0].operator.type, 'boolean');
  assert.equal(entries[0].operator.operation, 'true');
  return evaluate(entries[0].leftValue, {json}) === true;
};
const valid = body => condition('Valid Request', {body});
const prepare = body => Object.fromEntries(node('Prepare Context').parameters.assignments.assignments.map(
  assignment => [assignment.name, evaluate(assignment.value, {body})],
));
const targets = (name, port = 'main', output = 0) => (workflow.connections[name]?.[port]?.[output] ?? []).map(link => link.node);
const response = json => ({
  status: evaluate(node('Respond to Webhook').parameters.options.responseCode, {json}),
  body: evaluate(node('Respond to Webhook').parameters.responseBody, {json}),
});
let checks = 0;
const failures = [];
function check(label, run) {
  try { run(); checks++; }
  catch (error) { failures.push(`${label}: ${error.message}`); }
}

check('Public webhook and invalid-request response', () => {
  assert.equal(node('Webhook').parameters.httpMethod, 'POST');
  assert.ok([undefined, 'none'].includes(node('Webhook').parameters.authentication));
  assert.equal(node('Webhook').credentials?.httpHeaderAuth, undefined);
  assert.equal(node('Webhook').parameters.responseMode, 'responseNode');
  assert.deepEqual(targets('Webhook'), ['Valid Request']);
  assert.deepEqual(targets('Valid Request', 'main', 0), ['Prepare Context']);
  assert.deepEqual(targets('Valid Request', 'main', 1), ['Invalid Request']);
  assert.equal(node('Invalid Request').parameters.options.responseCode, 422);
  assert.ok(JSON.parse(node('Invalid Request').parameters.responseBody).error);
});
check('Normal backend request and first message without session', () => {
  assert.equal(valid(fixture()), true);
  assert.equal(valid({...fixture(), sessionId: null}), true);
  assert.equal(valid({...fixture(), sessionId: undefined}), true);
});
for (const [label, change] of [
  ['missing body', () => undefined], ['null body', () => null],
  ['legacy schema', b => ({...b, schema_version: 1})],
  ['string schema', b => ({...b, schema_version: '2'})],
  ['missing history', b => ({...b, history: undefined})],
  ['object history', b => ({...b, history: {}})],
  ['missing context', b => ({...b, context: null})],
  ['missing location', b => ({...b, context: {}})],
  ['unknown scope', b => ({...b, context: {location: {scope: 'city'}}})],
  ['blank text without photo', b => ({...b, message: '  '})],
  ['message is not text', b => ({...b, message: 5})],
  ['oversized message', b => ({...b, message: 'x'.repeat(4001)})],
]) check(`Reject ${label}`, () => assert.equal(valid(change(fixture())), false));
for (const field of ['user_id', 'sessionId']) {
  for (const value of [0, -1, 1.5, '7', false, {}, Infinity, NaN]) {
    check(`Reject invalid ${field} ${String(value)}`, () => assert.equal(valid({...fixture(), [field]: value}), false));
  }
}
check('Reject missing authenticated user', () => assert.equal(valid({...fixture(), user_id: undefined}), false));

for (const axis of ['lat', 'lon']) {
  const bound = axis === 'lat' ? 90 : 180;
  for (const value of [null, undefined, '', '53.2', false, NaN, Infinity, -Infinity, bound + 0.1, -bound - 0.1]) {
    check(`Reject invalid ${axis} ${String(value)}`, () => {
      const body = fixture(); body.context.location.point[axis] = value;
      assert.equal(valid(body), false);
    });
  }
}
for (const point of [{lat: 0, lon: 0}, {lat: 90, lon: 180}, {lat: -90, lon: -180}]) {
  check(`Accept coordinate boundary ${JSON.stringify(point)}`, () => {
    const body = fixture(); body.context.location.point = point;
    assert.equal(valid(body), true);
  });
}
check('Missing coordinates have no location fallback', () => {
  const body = fixture();
  body.context.location = {scope: 'unknown', point: null};
  assert.equal(valid(body), true);
  body.context.location.point = {lat: 0, lon: 0};
  assert.equal(valid(body), false);
  body.context.location = {scope: 'selected_point', point: null};
  assert.equal(valid(body), false);
  body.context.location = {scope: 'region_center_approximation', point: {lat: 45, lon: 60}};
  assert.equal(valid(body), true);
});

for (const mediaType of ['image/jpeg', 'image/png', 'image/webp', 'image/gif']) {
  check(`Photo-only request ${mediaType}`, () => {
    const body = {...fixture(), message: '', image: 'YWJj', mediaType};
    assert.equal(valid(body), true);
    const prepared = prepare(body);
    assert.equal(condition('Has Photo', prepared), true);
    assert.equal(prepared.image, body.image);
    assert.equal(prepared.mediaType, mediaType);
    assert.equal(evaluate(node('Convert Photo').parameters.options.mimeType, {json: prepared}), mediaType);
    const extension = {'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'image/gif': 'gif'}[mediaType];
    assert.equal(evaluate(node('Convert Photo').parameters.options.fileName, {json: prepared}), `crop-photo.${extension}`);
  });
}
check('Supported multi-megabyte photo does not overflow validation regex', () => {
  // Base64 syntax only: actual image bytes/MIME are validated by Laravel.
  assert.equal(valid({...fixture(), image: 'a'.repeat(6000000)}), true);
});
for (const [label, patch] of [
  ['bad alphabet', {image: 'not-base64'}],
  ['data URI instead of raw base64', {image: 'data:image/jpeg;base64,YWJj'}],
  ['bad padding', {image: 'a==='}],
  ['unsupported mime', {image: 'YWJj', mediaType: 'image/svg+xml'}],
  ['missing mime', {image: 'YWJj', mediaType: undefined}],
  ['numeric image', {image: 10}], ['zero image', {image: 0}],
  ['false image', {image: false}], ['object image', {image: {data: 'YWJj'}}],
  ['oversized base64', {image: 'a'.repeat(7000001)}],
]) check(`Reject ${label}`, () => assert.equal(valid({...fixture(), ...patch}), false));
check('No photo bypasses conversion', () => {
  for (const image of [null, undefined, '']) {
    const body = {...fixture(), image};
    assert.equal(valid(body), true);
    const prepared = prepare(body);
    assert.equal(prepared.image, '');
    assert.equal(condition('Has Photo', prepared), false);
    assert.match(prepared.prompt, /Текущее фото: нет/);
  }
});

check('Prompt has bounded history and excludes stale weather', () => {
  const body = fixture();
  body.history = [{role: 'user', text: 'DO_NOT_INCLUDE_OLD_MESSAGE'}, ...Array.from({length: 10}, (_, i) => ({role: i % 2 ? 'assistant' : 'user', text: `history-${i}`}))];
  body.history[1].text = 'x'.repeat(1500) + 'TOO_LONG_HISTORY_SUFFIX';
  body.history[2] = {role: 'system', text: 'DO_NOT_INCLUDE_SYSTEM_ENTRY'};
  body.history[3] = null;
  const before = JSON.stringify(body);
  const prepared = prepare(body);
  for (const expected of [body.message, now, '53.2', '63.6', 'пшеница', 'history-9', 'OpenWeatherMap']) {
    assert.ok(prepared.prompt.includes(expected), expected);
  }
  for (const marker of ['DO_NOT_INCLUDE_OLD_MESSAGE', 'TOO_LONG_HISTORY_SUFFIX', 'DO_NOT_INCLUDE_SYSTEM_ENTRY', 'STALE_WEATHER_MARKER', 'Old provider']) {
    assert.equal(prepared.prompt.includes(marker), false, marker);
  }
  assert.equal(JSON.stringify(body), before, 'Prompt preparation must not mutate the payload');
});
check('Independent requests never share location or history', () => {
  const first = fixture(); first.history = [{role: 'user', text: 'FIRST_USER_HISTORY'}];
  const second = fixture(); second.user_id = 8; second.sessionId = null;
  second.context.location.point = {lat: 43.25, lon: 76.95};
  second.history = [{role: 'user', text: 'SECOND_USER_HISTORY'}];
  const promptOne = prepare(first).prompt;
  const promptTwo = prepare(second).prompt;
  assert.ok(promptOne.includes('FIRST_USER_HISTORY'));
  assert.equal(promptTwo.includes('FIRST_USER_HISTORY'), false);
  assert.equal(promptTwo.includes('53.2'), false);
  assert.ok(promptTwo.includes('43.25'));
  assert.ok(promptTwo.includes('SECOND_USER_HISTORY'));
  assert.equal(prepare(first).prompt, promptOne);
  assert.equal(workflow.nodes.some(n => /memory/i.test(n.type)), false, 'Backend owns per-user/session history');
});

for (const [name, operation] of [['Current Weather', 'currentWeather'], ['Weather Forecast', '5DayForecast']]) {
  check(`${name} uses only the current webhook coordinates`, () => {
    const weather = node(name);
    assert.equal(weather.type, 'n8n-nodes-base.openWeatherMapTool');
    assert.equal(weather.parameters.operation, operation);
    assert.equal(weather.parameters.locationSelection, 'coordinates');
    assert.equal(weather.parameters.format, 'metric');
    assert.equal(JSON.stringify(weather.parameters).includes('$fromAI'), false);
    assert.equal(weather.parameters.cityName, undefined);
    assert.equal(weather.onError, 'continueRegularOutput');
    assert.deepEqual(targets(name, 'ai_tool'), ['AI Agronomist']);
    for (const point of [{lat: 53.2, lon: 63.6}, {lat: 43.25, lon: 76.95}, {lat: 0, lon: 0}]) {
      const body = fixture(); body.context.location.point = point;
      assert.equal(evaluate(weather.parameters.latitude, {body}), String(point.lat));
      assert.equal(evaluate(weather.parameters.longitude, {body}), String(point.lon));
    }
    const body = fixture(); body.context.location = {scope: 'unknown', point: null};
    assert.equal(Number.isFinite(Number(evaluate(weather.parameters.latitude, {body}))), false);
    assert.equal(Number.isFinite(Number(evaluate(weather.parameters.longitude, {body}))), false);
  });
}
check('Binary route reaches vision agent with prompt intact', () => {
  assert.deepEqual(targets('Prepare Context'), ['Has Photo']);
  assert.deepEqual(targets('Has Photo', 'main', 0), ['Convert Photo']);
  assert.deepEqual(targets('Has Photo', 'main', 1), ['AI Agronomist']);
  assert.deepEqual(targets('Convert Photo'), ['AI Agronomist']);
  const converter = node('Convert Photo');
  assert.equal(converter.type, 'n8n-nodes-base.convertToFile');
  assert.equal(converter.parameters.operation, 'toBinary');
  assert.equal(converter.parameters.sourceProperty, 'image');
  assert.equal(converter.parameters.binaryPropertyName, 'image');
  const agent = node('AI Agronomist');
  assert.equal(agent.type, '@n8n/n8n-nodes-langchain.agent');
  assert.equal(agent.parameters.promptType, 'define');
  assert.equal(agent.parameters.options.passthroughBinaryImages, true);
  assert.equal(agent.parameters.options.enableStreaming, false);
  const prepared = prepare({...fixture(), image: 'YWJj'});
  // Convert to File emits json:{}, so the prompt must use the upstream reference.
  assert.equal(evaluate(agent.parameters.text, {json: {}, prepared}), prepared.prompt);
  assert.deepEqual(targets('Anthropic Chat Model', 'ai_languageModel'), ['AI Agronomist']);
  assert.equal(node('Anthropic Chat Model').type, '@n8n/n8n-nodes-langchain.lmChatAnthropic');
  assert.ok(node('Anthropic Chat Model').parameters.model.value);
  assert.deepEqual(targets('AI Agronomist'), ['Respond to Webhook']);
});

check('Successful output matches backend response contract', () => {
  const result = response({output: '  Проверьте влажность зерна.  '});
  assert.equal(result.status, 200);
  assert.equal(result.body.response, 'Проверьте влажность зерна.');
  assert.deepEqual(Object.keys(result.body), ['response']);
});
for (const output of [{}, {output: ''}, {output: ' \n '}, {output: null}, {output: {}}, {error: 'PRIVATE_PROVIDER_KEY'}, {output: 'partial', error: 'PRIVATE_PROVIDER_KEY'}]) {
  check(`Reject unusable response ${JSON.stringify(output)}`, () => {
    const result = response(output);
    assert.equal(result.status, 502);
    assert.equal(typeof result.body.error, 'string');
    assert.equal(JSON.stringify(result.body).includes('PRIVATE_PROVIDER_KEY'), false);
    assert.equal(result.body.response, undefined);
  });
}
check('Native graph is connected and avoids generic code/API wrappers', () => {
  assert.equal(nodes.size, workflow.nodes.length, 'Duplicate node names');
  assert.equal(new Set(workflow.nodes.map(n => n.id)).size, workflow.nodes.length, 'Duplicate node IDs');
  const allowed = new Set(['n8n-nodes-base.webhook', 'n8n-nodes-base.if', 'n8n-nodes-base.set', 'n8n-nodes-base.convertToFile', 'n8n-nodes-base.respondToWebhook', 'n8n-nodes-base.openWeatherMapTool', '@n8n/n8n-nodes-langchain.agent', '@n8n/n8n-nodes-langchain.lmChatAnthropic']);
  for (const item of workflow.nodes) assert.ok(allowed.has(item.type), `Unexpected node type ${item.type}`);
  for (const [source, ports] of Object.entries(workflow.connections)) {
    assert.ok(nodes.has(source), `Missing source ${source}`);
    for (const [port, outputs] of Object.entries(ports)) {
      for (const outgoing of outputs) for (const link of outgoing) {
        assert.ok(nodes.has(link.node), `Missing target ${link.node}`);
        assert.equal(link.type, port);
        assert.equal(link.index, 0);
      }
    }
  }
  assert.equal(node('AI Agronomist').onError, 'continueRegularOutput');
  assert.equal(workflow.settings.saveDataSuccessExecution, 'none');
  assert.equal(workflow.settings.saveDataErrorExecution, 'none');
  assert.equal(workflow.settings.saveManualExecutions, false);
});

if (failures.length) {
  console.error(`${checks} checks passed; ${failures.length} failed:\n${failures.map(failure => `- ${failure}`).join('\n')}`);
  process.exitCode = 1;
} else {
  console.log(`${checks} checks passed: native graph, webhook contract, coordinate isolation, photo routing, history and response errors.`);
  console.log('These checks evaluate saved expressions and connections; live n8n execution, credentials and model/weather calls require an integration test.');
}

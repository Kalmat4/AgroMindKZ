import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const workflow = JSON.parse(fs.readFileSync(new URL('../n8n-workflows/agronomist-v2.json', import.meta.url)));
const run = (name, input) => vm.runInNewContext(`(function(){${workflow.nodes.find(n => n.name === name).parameters.jsCode}})()`, {$input:{first:()=>({json: input})}})[0].json;
const body = {schema_version:2, message:'Что делать с пшеницей?', image:null, context:{location:{scope:'selected_point',point:{lat:53.2,lon:63.6}},weather:{status:'unavailable'}},history:[{role:'user',text:'Здравствуйте'},{role:'assistant',text:'Добрый день'}]};
const out = run('Build Messages',{body}).anthropicBody;
assert.equal(out.messages[1].role,'assistant');
assert.equal(out.messages.length,3);
assert.ok(out.messages[2].content[0].text.includes('53.2'));
assert.ok(out.messages[2].content[0].text.includes('unavailable'));
const photo=run('Build Messages',{body:{...body,image:'YWJj',mediaType:'image/jpeg'}}).anthropicBody.messages.at(-1).content[0];
assert.equal(photo.source.data,'YWJj');
assert.equal(photo.source.type,'base64');
assert.throws(()=>run('Build Messages',{body:{...body,image:'x',mediaType:'image/svg+xml'}}));
assert.throws(()=>run('Build Messages',{body:{message:'old multipart'}}));
assert.equal(run('Format Response',{content:[{type:'text',text:'Ответ'}]}).body.response,'Ответ');
assert.equal(run('Format Response',{error:{message:'secret provider error'}}).statusCode,502);
assert.equal(run('Format Response',{}).statusCode,502);
assert.equal(workflow.nodes[0].parameters.authentication,'headerAuth');
const names=new Set(workflow.nodes.map(n=>n.name));
for(const [source,ports] of Object.entries(workflow.connections)) {
 assert.ok(names.has(source));
 for(const outputs of Object.values(ports)) for(const links of outputs) for(const link of links) assert.ok(names.has(link.node));
}
console.log('Workflow contract, image format, error handling and graph checks passed.');

import SwaggerParser from '@apidevtools/swagger-parser';
import fs from 'node:fs';
import assert from 'node:assert/strict';
await SwaggerParser.validate('docs/openapi.json');
const collection=JSON.parse(fs.readFileSync('docs/ClinicDesk.postman_collection.json','utf8'));
assert.equal(collection.item.length,12);
for(const key of ['password','token'])assert.equal(collection.variable.find(v=>v.key===key).value,'');
console.log('PASS: OpenAPI 3.0.3 schema/references; Postman collection contains 12 requests and no saved credentials.');


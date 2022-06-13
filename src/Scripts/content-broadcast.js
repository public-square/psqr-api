#!/usr/bin/env node

// IMPORTANT: must include an environment variable: NODE_ENV=development

// If you have problems with Axios and nodejs, either uncomment line 4 or run line 5 on CLI
// process.env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
// npm config set strict-ssl false
const dotenv = require('dotenv').config();
const yargs = require('yargs');
const axios = require('axios').default;
const crypto = require('crypto');
const fs = require('fs');
const bencode = require('bencode');
const https = require('https');
const {parseJwk} = require('jose/jwk/parse');
const {CompactSign} = require('jose/jws/compact/sign');

const argv = yargs
    .option('skeleton', {
        alias: 's',
        description: 'Provide a skeleton to use with the command',
        type: 'string',
    })
    .option('data', {
        alias: 'd',
        description: 'Data to provide to the command',
        type: 'string',
    })
    .option('key', {
        alias: 'k',
        description: 'Path to Private Key File to use with the command',
        type: 'string',
    })
    .help()
    .alias('help', 'h')
    .argv;

if (process.env.NODE_ENV === 'development') {
    const httpsAgent = new https.Agent({
        rejectUnauthorized: false,
    });

    axios.defaults.httpsAgent = httpsAgent;

    // eslint-disable-next-line no-console
    console.log(process.env.NODE_ENV, 'RejectUnauthorized is disabled.');
}

async function main() {
    // get private key from file or CLI -- Assumes Path to File
    let userPrivateKey = JSON.parse(fs.readFileSync('./src/Scripts/tests/user/user000863999-private.jwk', 'utf8'));

    if (typeof argv.k != 'undefined' && fs.existsSync(argv.k) !== false) {
        userPrivateKey = JSON.parse(fs.readFileSync(argv.k, 'utf8'));
    }

    // get skeleton file from file or CLI -- Assumes Path to File
    let sFile = JSON.parse(fs.readFileSync('./src/Scripts/skeleton.json', 'utf8'));

    if (typeof argv.s != 'undefined' && fs.existsSync(argv.s) !== false) {
        sFile = JSON.parse(fs.readFileSync(argv.s, 'utf8'));
    }

    // get data (assumed string from cli)
    let data = 'They all lied. This is the best number: ' + Math.random();

    if (typeof argv.d != 'undefined' && argv.d) {
        data = argv.d;
    }

    // parse into JWK form
    let parsedPrivateJWK = await parseJwk(userPrivateKey);

    // create encoder
    const encoder = new TextEncoder();

    // parse to get user to create did.json url
    let parsedKid = userPrivateKey.kid.split(':');

    // get did.json url
    let didUrl = 'https://' + parsedKid[2] + '/' + parsedKid[3].split('#')[0] + '/did.json';

    // get pubKey data
    let getResponse = await axios.get(didUrl);

    // manipulate the data for our skeleton
    sFile.name = getResponse.data.keys[0].kid;
    sFile.created = Math.floor(new Date().getTime() / 1000);

    // TODO: Assumes static form of skeleton.json, add a catch for these values.
    sFile.info.publicSquare.package.publishDate = Math.floor(new Date().getTime() / 1000);
    sFile.info.publicSquare.package.body = data;

    // TODO: Bring in more data for the file

    // get infoHash from info section of skeleton
    let infoHash = crypto.createHash('sha1').update(bencode.encode(JSON.stringify(sFile.info))).digest('hex');

    // let pubKey = await parseJwk(getResponse.data.keys[0]);

    // detached JWS over JSON string representation of info element
    let detachedJws = await new CompactSign(encoder.encode(infoHash))
        .setProtectedHeader({
            alg: userPrivateKey.alg,
            kid: userPrivateKey.kid,
        })
        .sign(parsedPrivateJWK);

    sFile.provenance.jwk = getResponse.data.keys[0];
    sFile.provenance.signature = detachedJws;

    // set infoHash
    sFile.infoHash = infoHash;

    // create sha1 of file
    let sha1Sum = crypto.createHash('sha1').update(JSON.stringify(sFile)).digest('hex');

    // create jws with file as payload
    const jws = await new CompactSign(
        encoder.encode(
            JSON.stringify({
                file: JSON.stringify(sFile),
                infoHash: infoHash,
            }),
        ))
        .setProtectedHeader({
            alg: userPrivateKey.alg,
            kid: userPrivateKey.kid,
        })
        .sign(parsedPrivateJWK);

    let url = dotenv.parsed.TEST_ENDPOINT + '/api/broadcast/' + infoHash;

    try {
        const response = await axios({
            url: url,
            method: 'PUT',
            data: JSON.stringify({
                token: jws,
                hash: sha1Sum,
            }),
        });

        console.log(response.data);

    } catch (e) {
        console.log(e);
    }
}

main();

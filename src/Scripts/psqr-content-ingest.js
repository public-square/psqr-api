#!/usr/bin/env node

// IMPORTANT: must include an environment variable: NODE_ENV=development
const path = require('path');
const dotenv = require('dotenv').config({path: path.join(__dirname, '../../', '.env')});
const yargs = require('yargs');
const lineReader = require('line-by-line');
const fs = require('fs');
const crypto = require('crypto');
const {promisify} = require('util');

// HTTPS, Methods, etc.
const https = require('https');
const axios = require('axios').default;

// Jose
const {decodeProtectedHeader} = require('jose/util/decode_protected_header');
const {compactVerify} = require('jose/jws/compact/verify');
const {parseJwk} = require('jose/jwk/parse');

// ElasticSearch
const {Client} = require('@elastic/elasticsearch');
const client = new Client({
    node: 'https://' + dotenv.parsed.ELASTICSEARCH_USERNAME + ':' + dotenv.parsed.ELASTICSEARCH_PASSWORD + '@' + dotenv.parsed.ELASTICSEARCH_URL + ':' + dotenv.parsed.ELASTICSEARCH_PORT,
    ssl: {
        rejectUnauthorized: false,
    },
});

// Redis
const redis = require('redis');
const redisClient = redis.createClient({
    port: dotenv.parsed.REDIS_PORT ? dotenv.parsed.REDIS_PORT : 6379,
    host: dotenv.parsed.REDIS_HOST ? dotenv.parsed.REDIS_HOST : '127.0.0.1',
}).on('error', function (err) {
    console.error(err);
    process.exit();
}).on('connect', function () {
    console.info('Redis Connected');
});

redisClient.getAsync = promisify(redisClient.get).bind(redisClient);
redisClient.setAsync = promisify(redisClient.set).bind(redisClient);
redisClient.delAsync = promisify(redisClient.del).bind(redisClient);

const argv = yargs
    .option('file', {
        alias: 'f',
        description: 'Provide the file to parse',
        type: 'string',
    })
    .option('urls', {
        alias: 'u',
        description: 'Urls',
        type: 'array',
    })
    .option('feed', {
        alias: 'feed',
        description: 'Feed',
        type: 'boolean',
    })
    .option('search', {
        alias: 'search',
        description: 'Search',
        type: 'boolean',
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

/* eslint-disable require-await */
async function downloadFile(config, fileUrl, outputLocationPath) {
    const writer = fs.createWriteStream(outputLocationPath);

    config.method = 'get';
    config.url = fileUrl;
    config.responseType = 'stream';

    return axios(config).then((response) => new Promise((resolve, reject) => {

        response.data.pipe(writer);

        let error = null;

        writer.on('error', (err) => {
            error = err;
            writer.close();
            reject(err);
        });

        writer.on('close', () => {
            if (!error) {
                resolve(response);
            }
            // no need to call the reject here, as it will have been called in the
            // 'error' stream;
        });
    })).catch(function (err) {
        console.log(err.response.status);
        console.log(err.response.statusText);
        console.log('Error was encountered downloading file. Exiting now.');

        if (fs.existsSync(outputLocationPath)) {
            fs.rmSync(outputLocationPath);
        }

        process.exit();
    });
}
/* eslint-enable require-await */

async function main() {
    if (typeof argv.f !== 'undefined' && argv.f && (typeof argv.u !== 'undefined' && argv.u)) {
        throw new Error('You can only run this command on either a File or a set of urls. Not both at the same time.');
    }

    if (typeof argv.feed === 'undefined' && !argv.feed && (typeof argv.search === 'undefined' && !argv.search)) {
        throw new Error('You need at least one ES Index Endpoint Set for this Script.');
    }

    if (typeof argv.f !== 'undefined' && argv.f) {
        lineReadFile(argv.f, 'file');
    } else if (typeof argv.u !== 'undefined' && argv.u) {
        // iterate over list of urls, get etag of each
        let urls = argv.u;

        for (let i = 0; i < urls.length; i++) {
            // Formulate Redis Key for testing / creation
            let hash = crypto.createHash('sha1').update(urls[i]).digest('hex');
            let cachedKey = 'psqr:etag:' + hash;

            // get value of cached ETag
            let cachedEtag = await redisClient.getAsync(cachedKey);

            let config = {};

            if (cachedEtag != null) {
                config = {
                    headers: {
                        'If-None-Match': cachedEtag,
                    },
                    validateStatus: function (status) {
                        return status >= 200 && status <= 304;
                    },
                };
            }

            config.transformResponse = [];

            let filename = hash + '.txt';

            // get URL Response
            let urlResponse = await downloadFile(config, urls[i], filename);

            // get ETag
            let etag = urlResponse.headers.etag;

            // if etag is undefined, server is improperly configured, so skip
            if (typeof etag === 'undefined') {
                console.info('Improper Server Configuration: ' + urls[i]);
                fs.rmSync(filename);
                continue;
            }

            // if status code is 304, response has no changes, thus skip
            if (urlResponse.status == 304) {
                console.info('Unchanged: ' + urls[i]);
                fs.rmSync(filename);
                continue;
            }

            // create new or update key-value pair
            await redisClient.setAsync(cachedKey, etag);

            console.info('Url Processed: ' + urls[i]);

            // read through file line by line, delete file on "url" flag
            lineReadFile(filename, 'url');
        }
    } else {
        console.error('You need to supply a flag with this command.');
        yargs.showHelp();
    }

    redisClient.quit();

}

// helper for checking indices existence. Returns index value or null
Object.byString = function (o, s) {
    // convert indexes to properties
    s = s.replace(/\[(\w+)\]/g, '.$1');
    // strip a leading dot
    s = s.replace(/^\./, '');
    let a = s.split('.');
    for (let i = 0, n = a.length; i < n; ++i) {
        let k = a[i];

        if (typeof o !== 'object') {
            return null;
        }

        if (k in o) {
            o = o[k];
        } else {
            return null;
        }
    }
    return o;
};

/* eslint-disable require-await */
async function linesCounter (file) {
    return new Promise((resolve) => {
        let lr = new lineReader(file);
        let count = 0;

        lr.on('line', function () {
            count++;
        });

        lr.on('end', function () {
            return resolve(count);
        })
    });
}
/* eslint-enable require-await */

async function lineReadFile(file, type) {
    console.log('Counting lines in file');
    const totalLines = await linesCounter(file);

    console.log('There are ' + totalLines + ' total lines.');

    let currentLine = 0;

    // create new line reader
    let lr = new lineReader(file);

    lr.on('error', function (err) {
        throw new Error(err);
    });

    lr.on('line', async function (line) {
        currentLine++;

        if (currentLine % 100 === 0) {
            /* eslint-disable no-extra-parens */
            console.log(((currentLine / totalLines) * 100).toFixed(2) + '% lines done. ' + currentLine + ' of ' + totalLines + ' processed.');
            /* eslint-enable no-extra-parens */
        }

        // get current line
        let current = null;

        try {
            current = JSON.parse(line);
        } catch (e) {
            throw new Error('Error: Invalid JSON: ' + line);
        }

        // let sha1hash = current.broadcast.hash;
        let pubKey = await getPubKey(current);

        if (pubKey === null) {
            throw new Error('Error: Key Not Found: ' + current.infoHash);
        }

        // get payload and protectedHeader
        const {payload, _protectedHeader} = await compactVerify(current.broadcast.token, pubKey);

        // get decoder, parse out infoHash and stringified data
        const decoder = new TextDecoder();

        let payloadData = JSON.parse(decoder.decode(payload));

        // if payload is missing throw error
        if (!payloadData && typeof payloadData === 'undefined') {
            throw new Error('Error: Missing Payload with JWS for Index: ' + current.infoHash);
        }

        // if payload is missing one of either "infoHash" AND "file" throw error
        if (typeof payloadData.infoHash === 'undefined' && typeof payloadData.file === 'undefined') {
            throw new Error('Error: Malformed Payload for Index: ' + current.infoHash);
        }

        let infoHash = payloadData.infoHash;

        // interchangeable for -u or -f flags
        let data = typeof payloadData.file !== 'undefined' && payloadData.file ? JSON.parse(payloadData.file) : payloadData;

        // if the message infoHash and payload infoHash do not match, throw error and skip
        if (infoHash !== current.infoHash) {
            throw new Error('Error: The InfoHashes do not match.');
        }

        let body = {
            body: Object.byString(data, 'info.publicSquare.package.body'),
            broadcastDate: Object.byString(data, 'created'),
            description: Object.byString(data, 'info.publicSquare.package.description'),
            identity: Object.byString(data, 'provenance.jwk.kid') != null ? Object.byString(data, 'provenance.jwk.kid').split('#')[0] : 'null',
            key: Object.byString(data, 'provenance.jwk.kid'),
            infoHash: infoHash,
            blindhash: Object.byString(data, 'blindhash'),
            lang: Object.byString(data, 'info.publicSquare.package.lang'),
            metainfo: data,
            publishDate: Object.byString(data, 'info.publicSquare.package.publishDate'),
            title: Object.byString(data, 'info.publicSquare.package.title'),
            geo: Object.byString(data, 'info.publicSquare.package.geo.geoLocal'),
            politicalSubdivision: Object.byString(data, 'info.publicSquare.package.geo.politicalSubdivision'),
            contentReply: Object.byString(data, 'info.publicSquare.package.references.reply'),
            contentAmplify: Object.byString(data, 'info.publicSquare.package.references.amplify'),
            contentLike: Object.byString(data, 'info.publicSquare.package.references.like'),
        };

        // ContentIndex will Update or Create
        if (typeof argv.search !== 'undefined' && argv.search) {
            let contentIndex = typeof dotenv.parsed.CONTENT_INDEX != 'undefined' ? dotenv.parsed.CONTENT_INDEX : 'psqrsearch';

            await createIndexItem(contentIndex, body, infoHash);
        }

        // FeedIndex will Update or Create
        if (typeof argv.feed !== 'undefined' && argv.feed) {
            let feedIndex = typeof dotenv.parsed.FEED_INDEX != 'undefined' ? dotenv.parsed.FEED_INDEX : 'psqrfeed';

            await createIndexItem(feedIndex, body, infoHash);
        }


    });

    lr.on('end', function () {
        console.log('All Items have been digested.');

        // delete the file made from url endpoint
        if (type === 'url') {
            fs.rmSync(file);
        }
    });
}

async function createIndexItem(index, body, infoHash) {
    await client.index({
        index: index,
        id: infoHash,
        body: body,
    });
}

async function getPubKey(item) {
    let header = decodeProtectedHeader(item.broadcast.token);

    if (typeof header.kid === 'undefined') {
        throw new Error('Error: Malformed Header for Index: ' + item.infoHash);
    }

    // parse to get user to create did.json url
    let parsedKid = header.kid.split(':');

    // get didUrl
    let didUrl = 'https://' + parsedKid[2].split('#')[0];

    // get pubKey
    let getResponse = await axios.get(didUrl);

    let keys = getResponse.data.psqr.publicKeys;

    let pubKey = null;

    for (let key in keys) {
        if (keys[key].kid == header.kid) {
            pubKey = await parseJwk(keys[key]);
            break;
        }
    }

    return pubKey;
}

main();

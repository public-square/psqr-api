#!/usr/bin/env node

// IMPORTANT: must include an environment variable: NODE_ENV=development
const dotenv = require('dotenv').config();
const yargs = require('yargs');
const lineReader = require('line-by-line');
const fs = require('fs');

// HTTPS, Methods, etc.
const https = require('https');
const axios = require('axios').default;

// ElasticSearch
const {Client} = require('@elastic/elasticsearch');
const client = new Client({
    node: 'https://' + dotenv.parsed.ELASTICSEARCH_USERNAME + ':' + dotenv.parsed.ELASTICSEARCH_PASSWORD + '@' + dotenv.parsed.ELASTICSEARCH_URL + ':' + dotenv.parsed.ELASTICSEARCH_PORT,
    ssl: {
        rejectUnauthorized: false,
    },
});

const argv = yargs
    .option('file', {
        alias: 'f',
        description: 'Provide the file to parse',
        type: 'string',
    })
    .option('url', {
        alias: 'u',
        description: 'Url',
        type: 'string',
    })
    .option('purge', {
        alias: 'p',
        description: 'Purge Records',
        type: 'boolean',
    })
    .option('match', {
        alias: 'm',
        description: 'Options to match on [Title|title|t, Desc|desc|d, Image|image|i, Url|url|u]',
        type: 'array',
    })
    .option('sort', {
        alias: 's',
        description: 'Sort On PublishDate',
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

async function lineReadFile(file, type) {
    // create new line reader
    let lr = new lineReader(file);
    let fileObjects = [];

    lr.on('error', function (err) {
        throw new Error(err);
    });

    lr.on('line', async function (line) {
        let current = null;

        try {
            current = JSON.parse(line);
        } catch (e) {
            throw new Error('Error: Invalid JSON: ' + line);
        }

        fileObjects.push(current);
    });

    lr.on('end', async function () {
        console.log('All Items have been digested.');

        // delete the file made from url endpoint
        if (type === 'url') {
            fs.rmSync(file);
        }

        await checkDuplicates(fileObjects);
    });
}
/* eslint-enable require-await */

async function main() {
    if (typeof argv.f !== 'undefined' && argv.f && (typeof argv.u !== 'undefined' && argv.u)) {
        throw new Error('You can only run this command on either a File or a set of urls. Not both at the same time.');
    }

    if (typeof argv.f === 'undefined' && !argv.f && (typeof argv.u === 'undefined' && !argv.u)) {
        throw new Error('You need at least one option present to use this script.');
    }

    if (typeof argv.f !== 'undefined' && argv.f) {
        console.info('File Processed: ' + argv.f);

        lineReadFile(argv.f, 'file');
    } else if (typeof argv.u !== 'undefined' && argv.u) {
        // iterate over list of urls, get etag of each
        let url = argv.u;

        let config = {};

        config = {
            validateStatus: function (status) {
                return status >= 200 && status <= 304;
            },
        };

        config.transformResponse = [];

        let filename = 'temp-dupe-hunter-' + new Date().getTime() + '.jsonl';

        // get URL Response
        await downloadFile(config, url, filename);

        console.info('Url Processed: ' + url);

        // read through file line by line, delete file on "url" flag
        lineReadFile(filename, 'url');
    } else {
        console.error('You need to supply a flag with this command.');
        yargs.showHelp();
    }
}

async function deleteDocument(index, infoHash) {
    try {
        await client.delete({
            index: index,
            id: infoHash,
        });
    } catch (e) {
        console.log(e);
    }
}

async function purgeDocuments(arrayToCheck) {
    console.log('Found ' + arrayToCheck.length + ' for removal.');
    console.log('Infohashes of those to remove:');

    console.log(arrayToCheck);

    let contentIndex = typeof dotenv.parsed.CONTENT_INDEX != 'undefined' ? dotenv.parsed.CONTENT_INDEX : 'psqrsearch';
    let feedIndex = typeof dotenv.parsed.FEED_INDEX != 'undefined' ? dotenv.parsed.FEED_INDEX : 'psqrfeed';

    for (let i = 0; i < arrayToCheck.length; i++) {
        await deleteDocument(contentIndex, arrayToCheck[i]);
        await deleteDocument(feedIndex, arrayToCheck[i]);
    }

    console.log('All Items Deleted');
}

/* eslint-disable complexity */
async function checkDuplicates(arrayToCheck) {
    // if sort flag is set, sort on publishDate
    /* eslint-disable no-extra-parens */
    if (typeof argv.s !== 'undefined' && argv.s) {
        arrayToCheck.sort((a, b) => (a.publishDate > b.publishDate ? 1 : -1));
    }
    /* eslint-enable no-extra-parens */

    let mapObjects = [];
    let purgeRecords = [];

    for (let i = 0; i < arrayToCheck.length; i++) {
        let title = arrayToCheck[i].metainfo.info.publicSquare.package.title.trim();
        let desc = arrayToCheck[i].metainfo.info.publicSquare.package.description.trim();
        let url = arrayToCheck[i].metainfo.info.publicSquare.package.canonicalUrl.trim();
        let image = arrayToCheck[i].metainfo.info.publicSquare.package.image.trim();
        let infoHash = arrayToCheck[i].infoHash.trim();
        let publishDate = arrayToCheck[i].publishDate;

        // create object from items
        let item = {
            title: title,
            desc: desc.length > 70 ? desc.substring(0, 67) + '...' : desc,
            url: url,
            image: image,
            infoHash: infoHash,
            publishDate: publishDate,
        };

        let matchOnTitle;
        let matchOnDesc;
        let matchOnUrl;
        let matchOnImage;

        if (typeof argv.m !== 'undefined' && argv.m) {
            /* eslint-disable no-undefined */
            matchOnTitle = argv.m.includes('Title') === true || argv.m.includes('title') === true || argv.m.includes('t') === true ? mapObjects.find((o) => Boolean(o.title) !== false && o.title === title) : undefined;
            matchOnDesc = argv.m.includes('Desc') === true || argv.m.includes('desc') === true || argv.m.includes('d') === true ? mapObjects.find((o) => Boolean(o.desc) !== false && o.desc === desc) : undefined;
            matchOnUrl = argv.m.includes('Url') === true || argv.m.includes('url') === true || argv.m.includes('u') === true ? mapObjects.find((o) => Boolean(o.url) !== false && o.url === url) : undefined;
            matchOnImage = argv.m.includes('Image') === true || argv.m.includes('image') === true || argv.m.includes('i') === true ? mapObjects.find((o) => Boolean(o.image) !== false && o.image === image) : undefined;
            /* eslint-enable no-undefined */
        } else {
            matchOnTitle = mapObjects.find((o) => Boolean(o.title) !== false && o.title === title);
            matchOnDesc = mapObjects.find((o) => Boolean(o.desc) !== false && o.desc === desc);
            matchOnUrl = mapObjects.find((o) => Boolean(o.url) !== false && o.url === url);
            matchOnImage = mapObjects.find((o) => Boolean(o.image) !== false && o.image === image);
        }

        /* eslint-disable no-undefined */
        switch (true) {
            case matchOnUrl !== undefined:
                console.log('Match Found on Url');
                console.log(matchOnUrl);
                console.log(item);
                console.log('\n');
                purgeRecords.push(item.infoHash);
                break;
            case matchOnTitle !== undefined:
                console.log('Match Found on Title');
                console.log(matchOnTitle);
                console.log(item);
                console.log('\n');
                purgeRecords.push(item.infoHash);
                break;
            case matchOnDesc !== undefined:
                console.log('Match Found on Description');
                console.log(matchOnDesc);
                console.log(item);
                console.log('\n');
                purgeRecords.push(item.infoHash);
                break;
            case matchOnImage !== undefined:
                console.log('Match Found on Image');
                console.log(matchOnImage);
                console.log(item);
                console.log('\n');
                purgeRecords.push(item.infoHash);
                break;
            default:
                // nothing has matched - add it to the array
                mapObjects.push(item);
                break;
        }
        /* eslint-enable no-undefined */
    }

    typeof argv.p !== 'undefined' && argv.p ? await purgeDocuments(purgeRecords) : console.log(purgeRecords);
}
/* eslint-enable complexity */

main();

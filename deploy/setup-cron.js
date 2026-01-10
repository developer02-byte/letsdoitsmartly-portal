/**
 * Set up cron job via cPanel API
 */
const https = require('https');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV'
};

const cronCommand = 'curl -s "https://letsdoitsmartly.com/portal/cron_sync.php?key=LDS_CRON_8f4e2b9a1c7d3e6f5a0b" > /dev/null 2>&1';

async function addCronJob() {
  return new Promise((resolve) => {
    // Try cPanel API 2 format
    const options = {
      hostname: config.hostname,
      port: config.port,
      path: `/cpsess0/execute/Cron/add_line?command=${encodeURIComponent(cronCommand)}&day=*&hour=*&minute=*/5&month=*&weekday=*`,
      method: 'GET',
      headers: {
        'Authorization': 'cpanel ' + config.username + ':' + config.token
      },
      rejectUnauthorized: false
    };

    console.log('Trying path:', options.path.substring(0, 100) + '...');

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        console.log('Status:', res.statusCode);
        console.log('Response:', data.substring(0, 500));
        resolve(data);
      });
    });

    req.on('error', (e) => {
      console.log('Error:', e.message);
      resolve(null);
    });
    req.end();
  });
}

async function listCronJobs() {
  return new Promise((resolve) => {
    const options = {
      hostname: config.hostname,
      port: config.port,
      path: '/execute/Cron/list_cron',
      method: 'GET',
      headers: {
        'Authorization': 'cpanel ' + config.username + ':' + config.token
      },
      rejectUnauthorized: false
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const result = JSON.parse(data);
          console.log('\nExisting cron jobs:');
          if (result.data) {
            result.data.forEach((job, i) => {
              console.log(`${i + 1}. ${job.minute} ${job.hour} ${job.day} ${job.month} ${job.weekday} - ${job.command.substring(0, 60)}...`);
            });
          }
        } catch (e) {
          console.log('List response:', data.substring(0, 200));
        }
        resolve(data);
      });
    });

    req.on('error', (e) => {
      console.log('Error:', e.message);
      resolve(null);
    });
    req.end();
  });
}

async function main() {
  console.log('Setting up cron job for sync...\n');
  console.log('Command:', cronCommand);
  console.log('Schedule: Every 5 minutes (*/5 * * * *)\n');

  await addCronJob();
  await listCronJobs();
}

main();

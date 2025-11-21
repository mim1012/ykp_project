const http = require('http');

const options = {
  hostname: 'localhost',
  port: 8000,
  path: '/api/stores',
  method: 'GET',
  headers: {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
};

const req = http.request(options, (res) => {
  console.log(`상태 코드: ${res.statusCode}`);
  console.log('응답 헤더:', JSON.stringify(res.headers, null, 2));
  
  let data = '';
  res.on('data', (chunk) => {
    data += chunk;
  });
  
  res.on('end', () => {
    console.log('\n응답 본문 (처음 500자):');
    console.log(data.substring(0, 500));
  });
});

req.on('error', (error) => {
  console.error('요청 실패:', error);
});

req.end();

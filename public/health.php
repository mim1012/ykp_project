<?php
// Ultra simple health check with PHP version info
http_response_code(200);
echo "OK-v3|PHP:" . PHP_VERSION . "|INT_SIZE:" . PHP_INT_SIZE;
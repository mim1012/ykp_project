"""
API Connector Module - 2025 Enhanced Implementation

Implements latest Binance and Bybit API connections with:
- Binance: Pegged Orders, Ed25519 authentication, microsecond precision
- Bybit: V5 Unified Account, cross-asset margin, RPI orders
- Real-time WebSocket streaming with automatic reconnection
- Rate limiting compliance and geographic endpoint optimization
- Enhanced security with API key encryption

Based on 2025년 9월 1일 API 명세서 분석
"""

from typing import Dict, List, Optional, Any, Callable, Union
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from datetime import datetime, timezone
from enum import Enum
import asyncio
import aiohttp
import websockets
import json
import hmac
import hashlib
import time
import base64
from urllib.parse import urlencode
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ed25519
from cryptography.hazmat.primitives.serialization import Encoding, PrivateFormat, NoEncryption
import re
from collections import defaultdict, deque

from .logger import SystemLogger


class OrderSide(Enum):
    """Order sides"""
    BUY = "BUY"
    SELL = "SELL"

class OrderType(Enum):
    """Order types for both exchanges"""
    MARKET = "MARKET"
    LIMIT = "LIMIT"
    STOP_LOSS = "STOP_LOSS"
    STOP_LOSS_LIMIT = "STOP_LOSS_LIMIT"
    TAKE_PROFIT = "TAKE_PROFIT"
    TAKE_PROFIT_LIMIT = "TAKE_PROFIT_LIMIT"
    LIMIT_MAKER = "LIMIT_MAKER"
    # Binance 2025 additions
    PEGGED_PRIMARY = "PEGGED_PRIMARY"
    PEGGED_MARKET = "PEGGED_MARKET"
    # Bybit V5 additions
    CONDITIONAL = "CONDITIONAL"
    RPI = "RPI"

class TimeInForce(Enum):
    """Time in force options"""
    GTC = "GTC"  # Good Till Cancelled
    IOC = "IOC"  # Immediate or Cancel
    FOK = "FOK"  # Fill or Kill
    POST_ONLY = "PostOnly"
    RPI = "RPI"  # Retail Price Improvement

@dataclass
class PeggedOrderParams:
    """Pegged order parameters for Binance"""
    peg_price_type: str = "PRIMARY_PEG"  # PRIMARY_PEG or MARKET_PEG
    peg_offset_type: str = "BIPS"  # BIPS or PERCENTAGE
    peg_offset_value: float = 0.0

@dataclass
class OrderRequest:
    """Enhanced order request data structure"""
    symbol: str
    side: OrderSide
    size: float
    order_type: OrderType
    price: Optional[float] = None
    time_in_force: TimeInForce = TimeInForce.GTC
    reduce_only: bool = False
    post_only: bool = False
    client_order_id: Optional[str] = None
    
    # Binance specific
    pegged_params: Optional[PeggedOrderParams] = None
    stop_price: Optional[float] = None
    
    # Bybit specific
    trigger_price: Optional[float] = None
    stop_loss: Optional[float] = None
    take_profit: Optional[float] = None
    category: str = "linear"  # spot, linear, inverse, option
    
    # Risk management
    max_slippage: float = 0.01  # 1% default
    position_idx: int = 0  # For hedge mode


@dataclass
class OrderResponse:
    """Enhanced order response data structure"""
    order_id: str
    client_order_id: str
    symbol: str
    side: str
    size: float
    price: float
    status: str
    filled_size: float
    avg_fill_price: float
    timestamp: datetime
    raw_response: Dict[str, Any]
    
    # Enhanced fields for 2025
    commission: float = 0.0
    commission_asset: str = ""
    is_pegged: bool = False
    peg_info: Optional[Dict[str, Any]] = None
    slippage: float = 0.0
    execution_time_ms: float = 0.0


@dataclass
class OrderbookData:
    """Orderbook data structure"""
    symbol: str
    bids: List[List[float]]  # [[price, quantity], ...]
    asks: List[List[float]]  # [[price, quantity], ...]
    timestamp: datetime
    level: int = 100  # Default depth

@dataclass
class MarketData:
    """Enhanced market data structure"""
    symbol: str
    price: float
    bid: float
    ask: float
    volume_24h: float
    timestamp: datetime
    
    # Enhanced fields for 2025
    spread: float = 0.0
    spread_percentage: float = 0.0
    funding_rate: Optional[float] = None
    open_interest: Optional[float] = None
    mark_price: Optional[float] = None
    index_price: Optional[float] = None
    price_change_24h: float = 0.0
    price_change_percentage_24h: float = 0.0


@dataclass
class PositionData:
    """Position data structure"""
    symbol: str
    side: str
    size: float
    entry_price: float
    mark_price: float
    unrealized_pnl: float
    margin: float
    timestamp: datetime


class RateLimiter:
    """Rate limiter for API requests"""
    
    def __init__(self, max_requests: int, window_seconds: int):
        self.max_requests = max_requests
        self.window_seconds = window_seconds
        self.requests = deque()
        self.lock = asyncio.Lock()
    
    async def acquire(self):
        async with self.lock:
            now = time.time()
            # Remove old requests outside the window
            while self.requests and now - self.requests[0] > self.window_seconds:
                self.requests.popleft()
            
            if len(self.requests) >= self.max_requests:
                sleep_time = self.window_seconds - (now - self.requests[0])
                if sleep_time > 0:
                    await asyncio.sleep(sleep_time)
                    return await self.acquire()
            
            self.requests.append(now)

class SecurityManager:
    """Security manager for API key encryption"""
    
    def __init__(self):
        self.fernet_key = Fernet.generate_key()
        self.fernet = Fernet(self.fernet_key)
    
    def encrypt_api_key(self, api_key: str) -> bytes:
        return self.fernet.encrypt(api_key.encode())
    
    def decrypt_api_key(self, encrypted_key: bytes) -> str:
        return self.fernet.decrypt(encrypted_key).decode()
    
    @staticmethod
    def generate_ed25519_keypair() -> tuple[str, str]:
        """Generate Ed25519 key pair for Binance"""
        private_key = ed25519.Ed25519PrivateKey.generate()
        public_key = private_key.public_key()
        
        private_pem = private_key.private_bytes(
            encoding=Encoding.PEM,
            format=PrivateFormat.PKCS8,
            encryption_algorithm=NoEncryption()
        ).decode()
        
        public_pem = public_key.public_bytes(
            encoding=Encoding.PEM,
            format=serialization.PublicFormat.SubjectPublicKeyInfo
        ).decode()
        
        return private_pem, public_pem

class BaseExchangeConnector(ABC):
    """Enhanced base class for exchange connectors with 2025 specifications"""
    
    def __init__(self, api_key: str, api_secret: str, logger: SystemLogger, 
                 testnet: bool = True, passphrase: Optional[str] = None):
        self.logger = logger
        self.testnet = testnet
        self.passphrase = passphrase
        
        # Security manager
        self.security_manager = SecurityManager()
        self.encrypted_api_key = self.security_manager.encrypt_api_key(api_key)
        self.encrypted_api_secret = self.security_manager.encrypt_api_key(api_secret)
        
        # Connection management
        self.session: Optional[aiohttp.ClientSession] = None
        self.ws_connections: Dict[str, Any] = {}
        self.is_connected_flag = False
        
        # Rate limiting
        self.rate_limiter = RateLimiter(max_requests=1200, window_seconds=60)
        
        # Performance tracking
        self.request_count = 0
        self.last_request_time = 0.0
        self.average_response_time = 0.0
        
        # Error handling
        self.max_retries = 3
        self.retry_delay = 1.0
        self.backoff_factor = 2.0
    
    @property
    def api_key(self) -> str:
        return self.security_manager.decrypt_api_key(self.encrypted_api_key)
    
    @property
    def api_secret(self) -> str:
        return self.security_manager.decrypt_api_key(self.encrypted_api_secret)
        
    @abstractmethod
    async def connect(self) -> bool:
        """Connect to exchange API"""
        pass
        
    @abstractmethod
    async def disconnect(self) -> None:
        """Disconnect from exchange API"""
        pass
        
    @abstractmethod
    async def place_order(self, order: OrderRequest) -> OrderResponse:
        """Place an order with enhanced features"""
        pass
    
    @abstractmethod
    async def place_batch_orders(self, orders: List[OrderRequest]) -> List[OrderResponse]:
        """Place multiple orders in batch"""
        pass
    
    @abstractmethod
    async def get_orderbook(self, symbol: str, limit: int = 100) -> OrderbookData:
        """Get orderbook data"""
        pass
        
    @abstractmethod
    async def cancel_order(self, symbol: str, order_id: str) -> bool:
        """Cancel an order"""
        pass
        
    @abstractmethod
    async def get_order_status(self, symbol: str, order_id: str) -> OrderResponse:
        """Get order status"""
        pass
        
    @abstractmethod
    async def get_positions(self) -> List[PositionData]:
        """Get all positions"""
        pass
        
    @abstractmethod
    async def get_balance(self) -> Dict[str, float]:
        """Get account balance"""
        pass
        
    @abstractmethod
    async def get_current_price(self, symbol: str) -> float:
        """Get current market price"""
        pass
        
    @abstractmethod
    def _generate_signature(self, params: Dict[str, Any]) -> str:
        """Generate API signature"""
        pass
        
    def is_connected(self) -> bool:
        """Check if connected to exchange"""
        return self.is_connected_flag
    
    async def _make_request(self, method: str, url: str, **kwargs) -> Dict[str, Any]:
        """Make authenticated request with rate limiting and retry logic"""
        await self.rate_limiter.acquire()
        
        for attempt in range(self.max_retries + 1):
            try:
                start_time = time.time()
                
                async with self.session.request(method, url, **kwargs) as response:
                    response_time = time.time() - start_time
                    self._update_performance_metrics(response_time)
                    
                    if response.status == 200:
                        return await response.json()
                    elif response.status == 429:  # Rate limit hit
                        retry_after = int(response.headers.get('Retry-After', self.retry_delay))
                        await asyncio.sleep(retry_after)
                        continue
                    else:
                        error_data = await response.json() if response.content_type == 'application/json' else {}
                        raise Exception(f"HTTP {response.status}: {error_data}")
                        
            except asyncio.TimeoutError:
                if attempt < self.max_retries:
                    wait_time = self.retry_delay * (self.backoff_factor ** attempt)
                    await asyncio.sleep(wait_time)
                    continue
                raise Exception("Request timeout after retries")
            except Exception as e:
                if attempt < self.max_retries:
                    wait_time = self.retry_delay * (self.backoff_factor ** attempt)
                    await asyncio.sleep(wait_time)
                    continue
                raise
        
        raise Exception("Max retries exceeded")
    
    def _update_performance_metrics(self, response_time: float):
        """Update performance tracking metrics"""
        self.request_count += 1
        self.last_request_time = time.time()
        
        if self.request_count == 1:
            self.average_response_time = response_time
        else:
            # Exponential moving average
            alpha = 0.1
            self.average_response_time = (alpha * response_time) + ((1 - alpha) * self.average_response_time)


class BinanceConnector(BaseExchangeConnector):
    """Enhanced Binance API connector with 2025 specifications"""
    
    def __init__(self, api_key: str, api_secret: str, logger: SystemLogger, 
                 testnet: bool = True, ed25519_private_key: Optional[str] = None):
        super().__init__(api_key, api_secret, logger, testnet)
        
        # 2025 Enhanced endpoints with geographic optimization
        if testnet:
            self.base_urls = [
                "https://testnet.binancefuture.com",
                "https://testnet.binance.vision"
            ]
            self.ws_base_url = "wss://stream.binancefuture.com"
            self.market_data_url = "https://testnet.binance.vision"  # Separate for market data
        else:
            self.base_urls = [
                "https://api.binance.com",
                "https://api-gcp.binance.com",
                "https://api1.binance.com",
                "https://api2.binance.com",
                "https://api3.binance.com",
                "https://api4.binance.com"
            ]
            self.ws_base_url = "wss://fstream.binance.com"
            self.market_data_url = "https://data-api.binance.vision"  # Dedicated market data
        
        self.base_url = self.base_urls[0]  # Primary endpoint
        self.recv_window = 10000  # 10 second timeout as per 2025 spec
        
        # Ed25519 authentication support
        self.ed25519_private_key = ed25519_private_key
        self.use_microseconds = False  # Can be enabled for high-frequency trading
        
        # Binance specific rate limiting (1200/min as per 2025 spec)
        self.rate_limiter = RateLimiter(max_requests=1200, window_seconds=60)
        
        # Market data callbacks
        self.market_data_callbacks: Dict[str, Callable] = {}
        
        # Order tracking for User Data Stream verification
        self.pending_orders: Dict[str, OrderRequest] = {}
        self.user_data_stream: Optional[str] = None
        
    async def connect(self) -> bool:
        """Enhanced connection with endpoint optimization"""
        try:
            # Create session with optimized settings
            timeout = aiohttp.ClientTimeout(total=30, sock_read=10)
            connector = aiohttp.TCPConnector(limit=100, ttl_dns_cache=300)
            self.session = aiohttp.ClientSession(timeout=timeout, connector=connector)
            
            # Test connectivity with all endpoints to find the fastest
            fastest_endpoint = await self._find_fastest_endpoint()
            if fastest_endpoint:
                self.base_url = fastest_endpoint
                self.is_connected_flag = True
                self.logger.info(f"Connected to Binance API via {self.base_url}")
                
                # Initialize User Data Stream for order verification
                await self._start_user_data_stream()
                return True
                    
        except Exception as e:
            self.logger.error(f"Failed to connect to Binance: {e}")
            
        return False
    
    async def _find_fastest_endpoint(self) -> Optional[str]:
        """Find fastest endpoint through latency testing"""
        tasks = []
        for endpoint in self.base_urls:
            task = asyncio.create_task(self._test_endpoint_latency(endpoint))
            tasks.append(task)
        
        results = await asyncio.gather(*tasks, return_exceptions=True)
        
        valid_results = [(endpoint, latency) for endpoint, (endpoint, latency) in zip(self.base_urls, results) 
                        if not isinstance(latency, Exception) and latency is not None]
        
        if valid_results:
            fastest = min(valid_results, key=lambda x: x[1])
            self.logger.info(f"Fastest endpoint: {fastest[0]} ({fastest[1]:.3f}s)")
            return fastest[0]
        
        return None
    
    async def _test_endpoint_latency(self, endpoint: str) -> tuple[str, Optional[float]]:
        """Test endpoint latency"""
        try:
            start_time = time.time()
            async with self.session.get(f"{endpoint}/fapi/v1/ping") as response:
                if response.status == 200:
                    latency = time.time() - start_time
                    return endpoint, latency
        except Exception:
            pass
        return endpoint, None
    
    async def _start_user_data_stream(self) -> None:
        """Start User Data Stream for order verification"""
        try:
            headers = {'X-MBX-APIKEY': self.api_key}
            async with self.session.post(
                f"{self.base_url}/fapi/v1/listenKey",
                headers=headers
            ) as response:
                if response.status == 200:
                    result = await response.json()
                    self.user_data_stream = result['listenKey']
                    
                    # Start WebSocket connection for user data
                    asyncio.create_task(self._maintain_user_data_stream())
                    self.logger.info("User Data Stream started")
        except Exception as e:
            self.logger.error(f"Failed to start User Data Stream: {e}")
        
    async def disconnect(self) -> None:
        """Disconnect from Binance API"""
        self.is_connected_flag = False
        
        # Close WebSocket connections
        for stream, ws in self.ws_connections.items():
            try:
                await ws.close()
                self.logger.info(f"Closed WebSocket stream: {stream}")
            except Exception as e:
                self.logger.error(f"Error closing WebSocket {stream}: {e}")
                
        self.ws_connections.clear()
        
        # Close HTTP session
        if self.session:
            await self.session.close()
            self.logger.info("Disconnected from Binance API")
            
    async def place_order(self, order: OrderRequest) -> OrderResponse:
        """Enhanced order placement with 2025 features"""
        start_time = time.time()
        
        try:
            # Build parameters with enhanced features
            params = self._build_order_params(order)
            
            # Add 2025 enhanced features
            if order.pegged_params:
                params.update(self._build_pegged_params(order.pegged_params))
            
            # Generate signature (Ed25519 or HMAC)
            if self.ed25519_private_key:
                params['signature'] = self._generate_ed25519_signature(params)
            else:
                params['signature'] = self._generate_signature(params)
            
            headers = {
                'X-MBX-APIKEY': self.api_key,
                'Content-Type': 'application/x-www-form-urlencoded'
            }
            
            # Add microsecond precision if enabled
            if self.use_microseconds:
                headers['X-MBX-TIME-UNIT'] = 'MICROSECOND'
            
            # Store order for User Data Stream verification
            client_order_id = params.get('newClientOrderId', f"order_{int(time.time() * 1000000)}")
            self.pending_orders[client_order_id] = order
            
            # Place order with timeout handling
            try:
                result = await self._make_request(
                    'POST',
                    f"{self.base_url}/fapi/v1/order",
                    data=params,
                    headers=headers
                )
                
                response = self._parse_order_response(result)
                response.execution_time_ms = (time.time() - start_time) * 1000
                
                return response
                
            except asyncio.TimeoutError:
                # Order might still be placed - verify via User Data Stream
                self.logger.warning(f"Order timeout - verifying via User Data Stream")
                await asyncio.sleep(1)  # Wait for potential User Data Stream update
                
                # Check if order was actually placed
                if client_order_id in self.pending_orders:
                    # Order status unknown - check manually
                    try:
                        return await self.get_order_status(order.symbol, client_order_id)
                    except:
                        raise Exception("Order timeout - status unknown")
                else:
                    # Order was confirmed via User Data Stream
                    raise Exception("Order timeout but may be placed - check positions")
                    
        except Exception as e:
            self.logger.error(f"Failed to place order: {e}")
            raise
    
    def _build_order_params(self, order: OrderRequest) -> Dict[str, Any]:
        """Build order parameters with 2025 enhancements"""
        timestamp = int(time.time() * (1000000 if self.use_microseconds else 1000))
        
        params = {
            'symbol': order.symbol,
            'side': order.side.value,
            'type': order.order_type.value,
            'quantity': str(order.size),
            'timestamp': timestamp,
            'recvWindow': self.recv_window
        }
        
        if order.client_order_id:
            params['newClientOrderId'] = order.client_order_id
        
        # Price handling
        if order.price and order.order_type not in [OrderType.MARKET]:
            params['price'] = str(order.price)
        
        # Stop price for stop orders
        if order.stop_price:
            params['stopPrice'] = str(order.stop_price)
        
        # Time in force
        if order.order_type != OrderType.MARKET:
            params['timeInForce'] = order.time_in_force.value
        
        # Position side for hedge mode
        if order.position_idx != 0:
            params['positionSide'] = 'LONG' if order.position_idx == 1 else 'SHORT'
        
        # Reduce only
        if order.reduce_only:
            params['reduceOnly'] = 'true'
        
        # Post only
        if order.post_only:
            params['timeInForce'] = 'GTX'
        
        return params
    
    def _build_pegged_params(self, pegged: PeggedOrderParams) -> Dict[str, Any]:
        """Build pegged order parameters (2025 feature)"""
        return {
            'pegPriceType': pegged.peg_price_type,
            'pegOffsetType': pegged.peg_offset_type,
            'pegOffsetValue': str(pegged.peg_offset_value)
        }
    
    async def place_batch_orders(self, orders: List[OrderRequest]) -> List[OrderResponse]:
        """Place multiple orders in batch"""
        batch_params = []
        
        for order in orders:
            params = self._build_order_params(order)
            if order.pegged_params:
                params.update(self._build_pegged_params(order.pegged_params))
            batch_params.append(params)
        
        # Sign batch request
        batch_data = {
            'batchOrders': json.dumps(batch_params),
            'timestamp': int(time.time() * (1000000 if self.use_microseconds else 1000)),
            'recvWindow': self.recv_window
        }
        
        batch_data['signature'] = self._generate_signature(batch_data)
        
        headers = {'X-MBX-APIKEY': self.api_key}
        
        result = await self._make_request(
            'POST',
            f"{self.base_url}/fapi/v1/batchOrders",
            data=batch_data,
            headers=headers
        )
        
        return [self._parse_order_response(order_result) for order_result in result]
            
    async def cancel_order(self, symbol: str, order_id: str) -> bool:
        """Cancel order on Binance"""
        try:
            params = {
                'symbol': symbol,
                'orderId': order_id,
                'timestamp': int(time.time() * (1000000 if self.use_microseconds else 1000)),
                'recvWindow': self.recv_window
            }
            
            params['signature'] = self._generate_signature(params)
            headers = {'X-MBX-APIKEY': self.api_key}
            
            result = await self._make_request(
                'DELETE',
                f"{self.base_url}/fapi/v1/order",
                params=params,
                headers=headers
            )
            
            self.logger.info(f"Order {order_id} cancelled successfully")
            return True
                    
        except Exception as e:
            self.logger.error(f"Failed to cancel order {order_id}: {e}")
            
        return False
        
    async def get_order_status(self, symbol: str, order_id: str) -> OrderResponse:
        """Get order status from Binance"""
        try:
            params = {
                'symbol': symbol,
                'orderId': order_id,
                'timestamp': int(time.time() * (1000000 if self.use_microseconds else 1000)),
                'recvWindow': self.recv_window
            }
            
            params['signature'] = self._generate_signature(params)
            headers = {'X-MBX-APIKEY': self.api_key}
            
            result = await self._make_request(
                'GET',
                f"{self.base_url}/fapi/v1/order",
                params=params,
                headers=headers
            )
            
            return self._parse_order_response(result)
                    
        except Exception as e:
            self.logger.error(f"Failed to get order status: {e}")
            raise
            
    async def get_positions(self) -> List[PositionData]:
        """Get all positions from Binance"""
        try:
            params = {
                'timestamp': int(time.time() * (1000000 if self.use_microseconds else 1000)),
                'recvWindow': self.recv_window
            }
            
            params['signature'] = self._generate_signature(params)
            headers = {'X-MBX-APIKEY': self.api_key}
            
            result = await self._make_request(
                'GET',
                f"{self.base_url}/fapi/v2/positionRisk",
                params=params,
                headers=headers
            )
            
            return [self._parse_position_data(pos) for pos in result if float(pos['positionAmt']) != 0]
                    
        except Exception as e:
            self.logger.error(f"Failed to get positions: {e}")
            
        return []
        
    async def get_balance(self) -> Dict[str, float]:
        """Get account balance from Binance"""
        try:
            params = {
                'timestamp': int(time.time() * (1000000 if self.use_microseconds else 1000)),
                'recvWindow': self.recv_window
            }
            
            params['signature'] = self._generate_signature(params)
            headers = {'X-MBX-APIKEY': self.api_key}
            
            result = await self._make_request(
                'GET',
                f"{self.base_url}/fapi/v2/account",
                params=params,
                headers=headers
            )
            
            return {
                'USDT': float(result.get('totalWalletBalance', 0)),
                'available': float(result.get('availableBalance', 0)),
                'margin': float(result.get('totalInitialMargin', 0))
            }
                    
        except Exception as e:
            self.logger.error(f"Failed to get balance: {e}")
            
        return {}
        
    async def get_current_price(self, symbol: str) -> float:
        """Get current price for symbol"""
        try:
            result = await self._make_request(
                'GET',
                f"{self.base_url}/fapi/v1/ticker/price",
                params={'symbol': symbol}
            )
            
            return float(result['price'])
                    
        except Exception as e:
            self.logger.error(f"Failed to get current price for {symbol}: {e}")
            
        return 0.0
        
    async def subscribe_market_data(self, symbols: List[str], callback: Callable) -> None:
        """Subscribe to market data WebSocket stream"""
        streams = [f"{symbol.lower()}@ticker" for symbol in symbols]
        stream_name = "/".join(streams)
        
        try:
            ws_url = f"{self.ws_base_url}/ws/{stream_name}"
            ws = await websockets.connect(ws_url)
            self.ws_connections[stream_name] = ws
            
            # Start listening task
            asyncio.create_task(self._handle_market_data(ws, callback))
            self.logger.info(f"Subscribed to market data: {symbols}")
            
        except Exception as e:
            self.logger.error(f"Failed to subscribe to market data: {e}")
            
    async def _handle_market_data(self, ws: Any, callback: Callable) -> None:
        """Handle incoming WebSocket market data"""
        try:
            async for message in ws:
                data = json.loads(message)
                market_data = self._parse_market_data(data)
                await callback(market_data)
                
        except Exception as e:
            self.logger.error(f"Error handling market data: {e}")
    
    def _generate_signature(self, params: Dict[str, Any]) -> str:
        """Generate HMAC SHA256 signature for Binance"""
        query_string = urlencode(params)
        return hmac.new(
            self.api_secret.encode('utf-8'),
            query_string.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()
    
    def _generate_ed25519_signature(self, params: Dict[str, Any]) -> str:
        """Generate Ed25519 signature for enhanced security (2025 feature)"""
        if not self.ed25519_private_key:
            raise ValueError("Ed25519 private key not provided")
        
        query_string = urlencode(params)
        
        # Load private key
        private_key = serialization.load_pem_private_key(
            self.ed25519_private_key.encode(),
            password=None
        )
        
        # Sign the message
        signature = private_key.sign(query_string.encode('utf-8'))
        return base64.b64encode(signature).decode('utf-8')
    
    async def _maintain_user_data_stream(self) -> None:
        """Maintain User Data Stream connection"""
        if not self.user_data_stream:
            return
        
        try:
            ws_url = f"{self.ws_base_url}/ws/{self.user_data_stream}"
            async with websockets.connect(ws_url) as websocket:
                while self.is_connected_flag:
                    try:
                        message = await asyncio.wait_for(websocket.recv(), timeout=30)
                        data = json.loads(message)
                        await self._process_user_data(data)
                    except asyncio.TimeoutError:
                        # Send ping to keep connection alive
                        await websocket.ping()
        except Exception as e:
            self.logger.error(f"User Data Stream error: {e}")
            # Attempt to restart stream
            await asyncio.sleep(5)
            await self._start_user_data_stream()
    
    async def _process_user_data(self, data: Dict[str, Any]) -> None:
        """Process User Data Stream messages"""
        event_type = data.get('e')
        
        if event_type == 'ORDER_TRADE_UPDATE':
            order_data = data.get('o', {})
            client_order_id = order_data.get('c')
            
            # Remove from pending orders if confirmed
            if client_order_id in self.pending_orders:
                del self.pending_orders[client_order_id]
                self.logger.info(f"Order {client_order_id} confirmed via User Data Stream")
    
    async def get_orderbook(self, symbol: str, limit: int = 100) -> OrderbookData:
        """Get orderbook data with enhanced depth support"""
        # Use market data endpoint for better performance
        url = f"{self.market_data_url}/fapi/v1/depth" if not self.testnet else f"{self.base_url}/fapi/v1/depth"
        
        params = {
            'symbol': symbol,
            'limit': min(limit, 1000)  # Max 1000 for institutions
        }
        
        result = await self._make_request('GET', url, params=params)
        
        return OrderbookData(
            symbol=symbol,
            bids=[[float(price), float(qty)] for price, qty in result['bids']],
            asks=[[float(price), float(qty)] for price, qty in result['asks']],
            timestamp=datetime.fromtimestamp(result['T'] / 1000),
            level=limit
        )
            
    def _parse_order_response(self, data: Dict[str, Any]) -> OrderResponse:
        """Parse order response from Binance API"""
        return OrderResponse(
            order_id=str(data['orderId']),
            client_order_id=data['clientOrderId'],
            symbol=data['symbol'],
            side=data['side'].lower(),
            size=float(data['origQty']),
            price=float(data.get('price', 0)),
            status=data['status'].lower(),
            filled_size=float(data.get('executedQty', 0)),
            avg_fill_price=float(data.get('avgPrice', 0)),
            timestamp=datetime.fromtimestamp(data['time'] / 1000),
            raw_response=data
        )
        
    def _parse_position_data(self, data: Dict[str, Any]) -> PositionData:
        """Parse position data from Binance API"""
        return PositionData(
            symbol=data['symbol'],
            side='long' if float(data['positionAmt']) > 0 else 'short',
            size=abs(float(data['positionAmt'])),
            entry_price=float(data['entryPrice']),
            mark_price=float(data['markPrice']),
            unrealized_pnl=float(data['unRealizedProfit']),
            margin=float(data['isolatedMargin']),
            timestamp=datetime.now()
        )
        
    def _parse_market_data(self, data: Dict[str, Any]) -> MarketData:
        """Parse market data from WebSocket stream"""
        return MarketData(
            symbol=data['s'],
            price=float(data['c']),
            bid=float(data['b']),
            ask=float(data['a']),
            volume_24h=float(data['v']),
            timestamp=datetime.fromtimestamp(data['E'] / 1000)
        )


class BybitConnector(BaseExchangeConnector):
    """Enhanced Bybit V5 API connector with Unified Account system"""
    
    def __init__(self, api_key: str, api_secret: str, logger: SystemLogger, testnet: bool = True):
        super().__init__(api_key, api_secret, logger, testnet)
        
        # Bybit V5 endpoints
        if testnet:
            self.base_url = "https://api-testnet.bybit.com"
            self.ws_base_url = "wss://stream-testnet.bybit.com"
        else:
            self.base_url = "https://api.bybit.com"
            self.ws_base_url = "wss://stream.bybit.com"
        
        # Bybit specific settings (120 requests/5sec as per 2025 spec)
        self.recv_window = 5000
        self.rate_limiter = RateLimiter(max_requests=120, window_seconds=5)
        
        # V5 API specific features
        self.unified_account = True  # Enable unified account by default
        self.supported_categories = ['spot', 'linear', 'inverse', 'option']
        
        # Slippage protection settings
        self.slippage_protection = True
        self.max_slippage_bps = 100  # 1% default
        
    async def connect(self) -> bool:
        """Connect to Bybit API with V5 endpoint testing"""
        try:
            # Create session with optimized settings
            timeout = aiohttp.ClientTimeout(total=30, sock_read=10)
            connector = aiohttp.TCPConnector(limit=100, ttl_dns_cache=300)
            self.session = aiohttp.ClientSession(timeout=timeout, connector=connector)
            
            # Test V5 API connectivity
            result = await self._make_request('GET', f"{self.base_url}/v5/market/time")
            
            if result.get('retCode') == 0:
                self.is_connected_flag = True
                self.logger.info(f"Connected to Bybit V5 API")
                return True
                
        except Exception as e:
            self.logger.error(f"Failed to connect to Bybit: {e}")
            
        return False
        
    async def disconnect(self) -> None:
        """Disconnect from Bybit API"""
        self.is_connected_flag = False
        
        # Close WebSocket connections
        for stream, ws in self.ws_connections.items():
            try:
                await ws.close()
                self.logger.info(f"Closed WebSocket stream: {stream}")
            except Exception as e:
                self.logger.error(f"Error closing WebSocket {stream}: {e}")
                
        self.ws_connections.clear()
        
        # Close HTTP session
        if self.session:
            await self.session.close()
            self.logger.info("Disconnected from Bybit V5 API")
    
    async def place_order(self, order: OrderRequest) -> OrderResponse:
        """Enhanced order placement with V5 Unified Account features"""
        start_time = time.time()
        
        try:
            # Build V5 order parameters
            params = self._build_v5_order_params(order)
            
            # Apply slippage protection for market orders
            if order.order_type == OrderType.MARKET and self.slippage_protection:
                params = await self._apply_slippage_protection(params, order)
            
            # Build headers
            timestamp = str(int(time.time() * 1000))
            headers = {
                'X-BAPI-API-KEY': self.api_key,
                'X-BAPI-TIMESTAMP': timestamp,
                'X-BAPI-RECV-WINDOW': str(self.recv_window),
                'Content-Type': 'application/json'
            }
            
            # Generate signature
            headers['X-BAPI-SIGN'] = self._generate_signature(timestamp, json.dumps(params))
            
            # Place order
            result = await self._make_request(
                'POST',
                f"{self.base_url}/v5/order/create",
                json=params,
                headers=headers
            )
            
            if result.get('retCode') == 0:
                response = self._parse_order_response(result['result'])
                response.execution_time_ms = (time.time() - start_time) * 1000
                return response
            else:
                raise Exception(f"Order failed: {result.get('retMsg', 'Unknown error')}")
                
        except Exception as e:
            self.logger.error(f"Failed to place order: {e}")
            raise
    
    def _build_v5_order_params(self, order: OrderRequest) -> Dict[str, Any]:
        """Build V5 API order parameters with Unified Account support"""
        params = {
            'category': order.category,
            'symbol': order.symbol,
            'side': order.side.value.capitalize(),
            'orderType': self._map_order_type(order.order_type),
            'qty': str(order.size)
        }
        
        # Client order ID
        if order.client_order_id:
            params['orderLinkId'] = order.client_order_id
        
        # Price for limit orders
        if order.price and order.order_type != OrderType.MARKET:
            params['price'] = str(order.price)
        
        # Time in force
        if order.order_type != OrderType.MARKET:
            params['timeInForce'] = self._map_time_in_force(order.time_in_force)
        
        # Conditional order features
        if order.trigger_price:
            params['triggerPrice'] = str(order.trigger_price)
            params['triggerDirection'] = 1 if order.side == OrderSide.BUY else 2
        
        # Take profit and stop loss
        if order.take_profit:
            params['takeProfit'] = str(order.take_profit)
        if order.stop_loss:
            params['stopLoss'] = str(order.stop_loss)
        
        # Reduce only
        if order.reduce_only:
            params['reduceOnly'] = True
        
        # Position index for hedge mode
        if order.position_idx != 0:
            params['positionIdx'] = order.position_idx
        
        return params
    
    def _map_order_type(self, order_type: OrderType) -> str:
        """Map internal order type to Bybit V5 format"""
        mapping = {
            OrderType.MARKET: 'Market',
            OrderType.LIMIT: 'Limit',
            OrderType.CONDITIONAL: 'Limit',  # Conditional orders are limit orders with trigger
            OrderType.RPI: 'Limit'  # RPI orders are special limit orders
        }
        return mapping.get(order_type, 'Limit')
    
    def _map_time_in_force(self, tif: TimeInForce) -> str:
        """Map time in force to Bybit V5 format"""
        mapping = {
            TimeInForce.GTC: 'GTC',
            TimeInForce.IOC: 'IOC',
            TimeInForce.FOK: 'FOK',
            TimeInForce.POST_ONLY: 'PostOnly',
            TimeInForce.RPI: 'PostOnly'  # RPI orders are post-only
        }
        return mapping.get(tif, 'GTC')
    
    async def _apply_slippage_protection(self, params: Dict[str, Any], order: OrderRequest) -> Dict[str, Any]:
        """Apply slippage protection by converting market order to IOC limit order"""
        try:
            # Get current market price
            current_price = await self.get_current_price(order.symbol)
            
            # Calculate slippage-protected price
            slippage_factor = 1 + (order.max_slippage if order.max_slippage else 0.01)
            
            if order.side == OrderSide.BUY:
                protected_price = current_price * slippage_factor
            else:
                protected_price = current_price / slippage_factor
            
            # Convert to IOC limit order
            params['orderType'] = 'Limit'
            params['price'] = str(protected_price)
            params['timeInForce'] = 'IOC'
            
            self.logger.info(f"Applied slippage protection: {current_price} -> {protected_price}")
            
        except Exception as e:
            self.logger.warning(f"Failed to apply slippage protection: {e}")
        
        return params
    
    async def place_batch_orders(self, orders: List[OrderRequest]) -> List[OrderResponse]:
        """Place multiple orders in batch (V5 feature)"""
        batch_params = []
        
        for order in orders:
            params = self._build_v5_order_params(order)
            if order.order_type == OrderType.MARKET and self.slippage_protection:
                params = await self._apply_slippage_protection(params, order)
            batch_params.append(params)
        
        # Group by category for batch processing
        categories = defaultdict(list)
        for i, params in enumerate(batch_params):
            categories[params['category']].append((i, params))
        
        all_responses = [None] * len(orders)
        
        # Process each category separately
        for category, category_orders in categories.items():
            timestamp = str(int(time.time() * 1000))
            
            batch_data = {
                'category': category,
                'request': [params for _, params in category_orders]
            }
            
            headers = {
                'X-BAPI-API-KEY': self.api_key,
                'X-BAPI-TIMESTAMP': timestamp,
                'X-BAPI-RECV-WINDOW': str(self.recv_window),
                'Content-Type': 'application/json'
            }
            
            headers['X-BAPI-SIGN'] = self._generate_signature(timestamp, json.dumps(batch_data))
            
            result = await self._make_request(
                'POST',
                f"{self.base_url}/v5/order/create-batch",
                json=batch_data,
                headers=headers
            )
            
            if result.get('retCode') == 0:
                batch_results = result.get('result', {}).get('list', [])
                for (original_index, _), order_result in zip(category_orders, batch_results):
                    if order_result.get('retCode') == 0:
                        all_responses[original_index] = self._parse_order_response(order_result)
        
        return [r for r in all_responses if r is not None]
    
    async def cancel_order(self, symbol: str, order_id: str) -> bool:
        """Cancel order on Bybit V5"""
        try:
            timestamp = str(int(time.time() * 1000))
            
            params = {
                'category': 'linear',  # Default category
                'symbol': symbol,
                'orderId': order_id
            }
            
            headers = {
                'X-BAPI-API-KEY': self.api_key,
                'X-BAPI-TIMESTAMP': timestamp,
                'X-BAPI-RECV-WINDOW': str(self.recv_window),
                'Content-Type': 'application/json'
            }
            
            headers['X-BAPI-SIGN'] = self._generate_signature(timestamp, json.dumps(params))
            
            result = await self._make_request(
                'POST',
                f"{self.base_url}/v5/order/cancel",
                json=params,
                headers=headers
            )
            
            if result.get('retCode') == 0:
                self.logger.info(f"Order {order_id} cancelled successfully")
                return True
            else:
                self.logger.error(f"Failed to cancel order: {result.get('retMsg')}")
                
        except Exception as e:
            self.logger.error(f"Failed to cancel order {order_id}: {e}")
            
        return False
    
    async def get_order_status(self, symbol: str, order_id: str) -> OrderResponse:
        """Get order status from Bybit V5"""
        try:
            timestamp = str(int(time.time() * 1000))
            
            params = {
                'category': 'linear',
                'symbol': symbol,
                'orderId': order_id
            }
            
            headers = {
                'X-BAPI-API-KEY': self.api_key,
                'X-BAPI-TIMESTAMP': timestamp,
                'X-BAPI-RECV-WINDOW': str(self.recv_window)
            }
            
            query_string = urlencode(params)
            headers['X-BAPI-SIGN'] = self._generate_signature(timestamp, query_string)
            
            result = await self._make_request(
                'GET',
                f"{self.base_url}/v5/order/realtime",
                params=params,
                headers=headers
            )
            
            if result.get('retCode') == 0:
                orders = result.get('result', {}).get('list', [])
                if orders:
                    return self._parse_order_response(orders[0])
                else:
                    raise Exception(f"Order {order_id} not found")
            else:
                raise Exception(f"Failed to get order status: {result.get('retMsg')}")
                
        except Exception as e:
            self.logger.error(f"Failed to get order status: {e}")
            raise
    
    async def get_positions(self) -> List[PositionData]:
        """Get all positions from Bybit V5"""
        try:
            timestamp = str(int(time.time() * 1000))
            
            params = {
                'category': 'linear',
                'settleCoin': 'USDT'
            }
            
            headers = {
                'X-BAPI-API-KEY': self.api_key,
                'X-BAPI-TIMESTAMP': timestamp,
                'X-BAPI-RECV-WINDOW': str(self.recv_window)
            }
            
            query_string = urlencode(params)
            headers['X-BAPI-SIGN'] = self._generate_signature(timestamp, query_string)
            
            result = await self._make_request(
                'GET',
                f"{self.base_url}/v5/position/list",
                params=params,
                headers=headers
            )
            
            if result.get('retCode') == 0:
                positions = result.get('result', {}).get('list', [])
                return [self._parse_position_data(pos) for pos in positions 
                       if float(pos['size']) != 0]
            else:
                raise Exception(f"Failed to get positions: {result.get('retMsg')}")
                
        except Exception as e:
            self.logger.error(f"Failed to get positions: {e}")
            return []
    
    def _parse_position_data(self, data: Dict[str, Any]) -> PositionData:
        """Parse position data from Bybit V5 API"""
        return PositionData(
            symbol=data['symbol'],
            side='long' if data['side'] == 'Buy' else 'short',
            size=abs(float(data['size'])),
            entry_price=float(data['avgPrice']) if data.get('avgPrice') else 0.0,
            mark_price=float(data['markPrice']) if data.get('markPrice') else 0.0,
            unrealized_pnl=float(data['unrealisedPnl']) if data.get('unrealisedPnl') else 0.0,
            margin=float(data['positionIM']) if data.get('positionIM') else 0.0,
            timestamp=datetime.now()
        )
    
    async def get_balance(self) -> Dict[str, float]:
        """Get account balance from Bybit V5 Unified Account"""
        try:
            balance_data = await self.get_unified_account_balance()
            
            if balance_data and 'list' in balance_data:
                account = balance_data['list'][0]  # First account (UNIFIED)
                coins = account.get('coin', [])
                
                balance = {}
                for coin in coins:
                    symbol = coin['coin']
                    balance[symbol] = {
                        'free': float(coin.get('availableToWithdraw', 0)),
                        'used': float(coin.get('locked', 0)),
                        'total': float(coin.get('walletBalance', 0))
                    }
                
                # Add total account value
                balance['total_equity'] = float(account.get('totalEquity', 0))
                balance['total_margin_balance'] = float(account.get('totalMarginBalance', 0))
                balance['total_available_balance'] = float(account.get('totalAvailableBalance', 0))
                
                return balance
                
        except Exception as e:
            self.logger.error(f"Failed to get balance: {e}")
            
        return {}
    
    async def get_unified_account_balance(self) -> Dict[str, Any]:
        """Get Unified Account balance with cross-asset margin info"""
        timestamp = str(int(time.time() * 1000))
        
        params = {
            'accountType': 'UNIFIED'
        }
        
        headers = {
            'X-BAPI-API-KEY': self.api_key,
            'X-BAPI-TIMESTAMP': timestamp,
            'X-BAPI-RECV-WINDOW': str(self.recv_window)
        }
        
        query_string = urlencode(params)
        headers['X-BAPI-SIGN'] = self._generate_signature(timestamp, query_string)
        
        result = await self._make_request(
            'GET',
            f"{self.base_url}/v5/account/wallet-balance",
            params=params,
            headers=headers
        )
        
        if result.get('retCode') == 0:
            return result.get('result', {})
        else:
            raise Exception(f"Failed to get balance: {result.get('retMsg')}")
    
    async def get_orderbook(self, symbol: str, limit: int = 25) -> OrderbookData:
        """Get orderbook data with V5 enhanced depth (up to 1000 levels)"""
        params = {
            'category': 'linear',  # Default to linear, can be parameterized
            'symbol': symbol,
            'limit': min(limit, 1000)  # V5 supports up to 1000 levels for institutions
        }
        
        result = await self._make_request(
            'GET',
            f"{self.base_url}/v5/market/orderbook",
            params=params
        )
        
        if result.get('retCode') == 0:
            data = result.get('result', {})
            return OrderbookData(
                symbol=symbol,
                bids=[[float(price), float(qty)] for price, qty in data.get('b', [])],
                asks=[[float(price), float(qty)] for price, qty in data.get('a', [])],
                timestamp=datetime.fromtimestamp(int(data.get('ts', 0)) / 1000),
                level=limit
            )
        else:
            raise Exception(f"Failed to get orderbook: {result.get('retMsg')}")
    
    def _generate_signature(self, timestamp: str, payload: str) -> str:
        """Generate HMAC SHA256 signature for Bybit V5"""
        sign_payload = timestamp + self.api_key + str(self.recv_window) + payload
        return hmac.new(
            self.api_secret.encode('utf-8'),
            sign_payload.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()
    
    async def get_current_price(self, symbol: str) -> float:
        """Get current price with V5 API"""
        try:
            params = {
                'category': 'linear',
                'symbol': symbol
            }
            
            result = await self._make_request(
                'GET',
                f"{self.base_url}/v5/market/tickers",
                params=params
            )
            
            if result.get('retCode') == 0:
                tickers = result.get('result', {}).get('list', [])
                if tickers:
                    return float(tickers[0]['lastPrice'])
            
            raise Exception(f"No price data for {symbol}")
            
        except Exception as e:
            self.logger.error(f"Failed to get current price for {symbol}: {e}")
            return 0.0
    
    async def subscribe_market_data(self, symbols: List[str], callback: Callable) -> None:
        """Subscribe to market data WebSocket stream for Bybit V5"""
        topics = []
        for symbol in symbols:
            topics.extend([
                f'tickers.{symbol}',
                f'orderbook.1.{symbol}',  # Level 1 orderbook
                f'publicTrade.{symbol}'
            ])
        
        ws_url = f"{self.ws_base_url}/v5/public/linear"
        
        async def handle_message(data):
            try:
                if 'topic' in data and 'data' in data:
                    topic = data['topic']
                    if topic.startswith('tickers.'):
                        market_data = self._parse_v5_market_data(data['data'])
                        await callback(market_data)
                    elif topic.startswith('orderbook.'):
                        orderbook_data = self._parse_v5_orderbook(data['data'])
                        await callback(orderbook_data)
            except Exception as e:
                self.logger.error(f"Error processing market data: {e}")
        
        # Subscribe to topics
        subscribe_msg = {
            'op': 'subscribe',
            'args': topics
        }
        
        try:
            async with websockets.connect(ws_url) as websocket:
                # Send subscription message
                await websocket.send(json.dumps(subscribe_msg))
                self.ws_connections['market_data'] = websocket
                
                # Handle messages
                async for message in websocket:
                    data = json.loads(message)
                    await handle_message(data)
                    
        except Exception as e:
            self.logger.error(f"WebSocket subscription failed: {e}")
    
    def _parse_v5_market_data(self, data: Dict[str, Any]) -> MarketData:
        """Parse V5 market data from WebSocket"""
        return MarketData(
            symbol=data['symbol'],
            price=float(data['lastPrice']),
            bid=float(data['bid1Price']),
            ask=float(data['ask1Price']),
            volume_24h=float(data['volume24h']),
            timestamp=datetime.fromtimestamp(int(data['ts']) / 1000),
            funding_rate=float(data.get('fundingRate', 0)),
            open_interest=float(data.get('openInterest', 0)),
            mark_price=float(data.get('markPrice', 0)),
            index_price=float(data.get('indexPrice', 0)),
            price_change_24h=float(data.get('price24hPcnt', 0)) * 100
        )
    
    def _parse_v5_orderbook(self, data: Dict[str, Any]) -> OrderbookData:
        """Parse V5 orderbook data from WebSocket"""
        return OrderbookData(
            symbol=data['s'],
            bids=[[float(price), float(qty)] for price, qty in data.get('b', [])],
            asks=[[float(price), float(qty)] for price, qty in data.get('a', [])],
            timestamp=datetime.fromtimestamp(int(data['ts']) / 1000),
            level=len(data.get('b', []))
        )
        
    def _parse_order_response(self, data: Dict[str, Any]) -> OrderResponse:
        """Parse order response from Bybit V5 API"""
        return OrderResponse(
            order_id=data['orderId'],
            client_order_id=data.get('orderLinkId', ''),
            symbol=data['symbol'],
            side=data['side'].lower(),
            size=float(data['qty']),
            price=float(data.get('price', 0)),
            status=data['orderStatus'].lower(),
            filled_size=float(data.get('cumExecQty', 0)),
            avg_fill_price=float(data.get('avgPrice', 0)) if data.get('avgPrice') else 0.0,
            timestamp=datetime.fromtimestamp(int(data['createdTime']) / 1000),
            raw_response=data
        )


# Enhanced WebSocket Manager for real-time data
class WebSocketManager:
    """Enhanced WebSocket manager with automatic reconnection"""
    
    def __init__(self, logger: SystemLogger):
        self.logger = logger
        self.connections: Dict[str, Any] = {}
        self.reconnect_attempts = defaultdict(int)
        self.max_reconnect_attempts = 5
        self.reconnect_delay = 1.0
        self.backoff_factor = 2.0
    
    async def connect_websocket(self, url: str, name: str, 
                              message_handler: Callable) -> None:
        """Connect to WebSocket with automatic reconnection"""
        while self.reconnect_attempts[name] < self.max_reconnect_attempts:
            try:
                self.logger.info(f"Connecting to WebSocket {name}: {url}")
                async with websockets.connect(url) as websocket:
                    self.connections[name] = websocket
                    self.reconnect_attempts[name] = 0  # Reset on successful connection
                    
                    # Handle messages
                    async for message in websocket:
                        try:
                            data = json.loads(message)
                            await message_handler(data)
                        except Exception as e:
                            self.logger.error(f"Error processing WebSocket message: {e}")
                            
            except (websockets.exceptions.ConnectionClosed, 
                   websockets.exceptions.InvalidStatusCode) as e:
                self.reconnect_attempts[name] += 1
                delay = self.reconnect_delay * (self.backoff_factor ** (self.reconnect_attempts[name] - 1))
                
                self.logger.warning(
                    f"WebSocket {name} connection failed (attempt {self.reconnect_attempts[name]}): {e}. "
                    f"Reconnecting in {delay}s..."
                )
                
                if self.reconnect_attempts[name] < self.max_reconnect_attempts:
                    await asyncio.sleep(delay)
                else:
                    self.logger.error(f"Max reconnection attempts reached for {name}")
                    break
                    
            except Exception as e:
                self.logger.error(f"Unexpected WebSocket error for {name}: {e}")
                break
    
    async def close_all(self) -> None:
        """Close all WebSocket connections"""
        for name, ws in self.connections.items():
            try:
                await ws.close()
                self.logger.info(f"Closed WebSocket: {name}")
            except Exception as e:
                self.logger.error(f"Error closing WebSocket {name}: {e}")
        
        self.connections.clear()


# Performance monitoring
class PerformanceMonitor:
    """Monitor API performance and health"""
    
    def __init__(self):
        self.metrics = {
            'total_requests': 0,
            'successful_requests': 0,
            'failed_requests': 0,
            'average_response_time': 0.0,
            'last_request_time': 0.0,
            'connection_uptime': 0.0
        }
        self.start_time = time.time()
    
    def record_request(self, success: bool, response_time: float) -> None:
        """Record request metrics"""
        self.metrics['total_requests'] += 1
        self.metrics['last_request_time'] = time.time()
        
        if success:
            self.metrics['successful_requests'] += 1
        else:
            self.metrics['failed_requests'] += 1
        
        # Update average response time (exponential moving average)
        alpha = 0.1
        if self.metrics['average_response_time'] == 0:
            self.metrics['average_response_time'] = response_time
        else:
            self.metrics['average_response_time'] = (
                alpha * response_time + 
                (1 - alpha) * self.metrics['average_response_time']
            )
    
    def get_health_status(self) -> Dict[str, Any]:
        """Get current health status"""
        uptime = time.time() - self.start_time
        success_rate = (
            self.metrics['successful_requests'] / self.metrics['total_requests']
            if self.metrics['total_requests'] > 0 else 0
        )
        
        return {
            'uptime_seconds': uptime,
            'success_rate': success_rate,
            'average_response_time_ms': self.metrics['average_response_time'] * 1000,
            'total_requests': self.metrics['total_requests'],
            'requests_per_minute': (
                self.metrics['total_requests'] / (uptime / 60)
                if uptime > 0 else 0
            )
        }


class ExchangeConnectorFactory:
    """Factory for creating exchange connectors"""
    
    @staticmethod
    def create_connector(exchange: str, api_key: str, api_secret: str, 
                        logger: SystemLogger, testnet: bool = True, 
                        **kwargs) -> BaseExchangeConnector:
        """Create exchange connector based on exchange name"""
        
        if exchange.lower() == 'binance':
            ed25519_private_key = kwargs.get('ed25519_private_key')
            return BinanceConnector(api_key, api_secret, logger, testnet, ed25519_private_key)
        elif exchange.lower() == 'bybit':
            return BybitConnector(api_key, api_secret, logger, testnet)
        else:
            raise ValueError(f"Unsupported exchange: {exchange}")


class MultiExchangeConnector:
    """Multi-exchange connector with failover support"""
    
    def __init__(self, logger: SystemLogger):
        self.logger = logger
        self.connectors: Dict[str, BaseExchangeConnector] = {}
        self.primary_exchange = None
        
    def add_exchange(self, name: str, connector: BaseExchangeConnector, 
                    is_primary: bool = False) -> None:
        """Add exchange connector"""
        self.connectors[name] = connector
        if is_primary or not self.primary_exchange:
            self.primary_exchange = name
    
    async def connect_all(self) -> Dict[str, bool]:
        """Connect to all exchanges"""
        results = {}
        for name, connector in self.connectors.items():
            try:
                results[name] = await connector.connect()
            except Exception as e:
                self.logger.error(f"Failed to connect to {name}: {e}")
                results[name] = False
        return results
    
    async def place_order_with_failover(self, order: OrderRequest, 
                                       preferred_exchange: Optional[str] = None) -> OrderResponse:
        """Place order with automatic failover"""
        exchanges_to_try = [preferred_exchange] if preferred_exchange else [self.primary_exchange]
        exchanges_to_try.extend([name for name in self.connectors.keys() 
                               if name not in exchanges_to_try])
        
        last_error = None
        for exchange_name in exchanges_to_try:
            if exchange_name not in self.connectors:
                continue
                
            connector = self.connectors[exchange_name]
            if not connector.is_connected():
                continue
                
            try:
                self.logger.info(f"Attempting to place order on {exchange_name}")
                return await connector.place_order(order)
            except Exception as e:
                last_error = e
                self.logger.warning(f"Order failed on {exchange_name}: {e}")
                continue
        
        raise Exception(f"Order failed on all exchanges. Last error: {last_error}")
    
    async def get_best_price(self, symbol: str) -> Dict[str, float]:
        """Get best price across all connected exchanges"""
        prices = {}
        for name, connector in self.connectors.items():
            if connector.is_connected():
                try:
                    price = await connector.get_current_price(symbol)
                    if price > 0:
                        prices[name] = price
                except Exception as e:
                    self.logger.warning(f"Failed to get price from {name}: {e}")
        return prices


class APIConnectorManager:
    """Main manager class for API connectors with enhanced 2025 features"""
    
    def __init__(self, logger: SystemLogger):
        self.logger = logger
        self.connectors: Dict[str, BaseExchangeConnector] = {}
        self.performance_monitor = PerformanceMonitor()
        self.websocket_manager = WebSocketManager(logger)
        
        # Health check settings
        self.health_check_interval = 30  # seconds
        self.health_check_task: Optional[asyncio.Task] = None
    
    def add_binance_connector(self, api_key: str, api_secret: str, 
                            testnet: bool = True, ed25519_private_key: Optional[str] = None) -> BinanceConnector:
        """Add Binance connector with 2025 enhancements"""
        connector = BinanceConnector(api_key, api_secret, self.logger, testnet, ed25519_private_key)
        self.connectors['binance'] = connector
        return connector
    
    def add_bybit_connector(self, api_key: str, api_secret: str, 
                          testnet: bool = True) -> BybitConnector:
        """Add Bybit V5 connector"""
        connector = BybitConnector(api_key, api_secret, self.logger, testnet)
        self.connectors['bybit'] = connector
        return connector
    
    async def connect_all(self) -> Dict[str, bool]:
        """Connect to all configured exchanges"""
        results = {}
        tasks = []
        
        for name, connector in self.connectors.items():
            task = asyncio.create_task(self._connect_with_monitoring(name, connector))
            tasks.append(task)
        
        completed_tasks = await asyncio.gather(*tasks, return_exceptions=True)
        
        for (name, _), result in zip(self.connectors.items(), completed_tasks):
            if isinstance(result, Exception):
                results[name] = False
                self.logger.error(f"Failed to connect to {name}: {result}")
            else:
                results[name] = result
        
        # Start health monitoring if any connections succeeded
        if any(results.values()):
            self.health_check_task = asyncio.create_task(self._health_check_loop())
        
        return results
    
    async def _connect_with_monitoring(self, name: str, connector: BaseExchangeConnector) -> bool:
        """Connect with performance monitoring"""
        start_time = time.time()
        
        try:
            success = await connector.connect()
            response_time = time.time() - start_time
            self.performance_monitor.record_request(success, response_time)
            
            if success:
                self.logger.info(f"Connected to {name} in {response_time:.3f}s")
            
            return success
            
        except Exception as e:
            response_time = time.time() - start_time
            self.performance_monitor.record_request(False, response_time)
            raise
    
    async def _health_check_loop(self) -> None:
        """Continuous health checking of connections"""
        while True:
            try:
                await asyncio.sleep(self.health_check_interval)
                
                for name, connector in self.connectors.items():
                    if not connector.is_connected():
                        self.logger.warning(f"Connection to {name} lost - attempting reconnection")
                        try:
                            await connector.connect()
                        except Exception as e:
                            self.logger.error(f"Failed to reconnect to {name}: {e}")
                
                # Log health status
                health = self.performance_monitor.get_health_status()
                self.logger.info(f"Health: {health['success_rate']:.2%} success, "
                               f"{health['average_response_time_ms']:.1f}ms avg response")
                
            except asyncio.CancelledError:
                break
            except Exception as e:
                self.logger.error(f"Health check error: {e}")
    
    async def shutdown(self) -> None:
        """Shutdown all connections and cleanup"""
        if self.health_check_task:
            self.health_check_task.cancel()
            try:
                await self.health_check_task
            except asyncio.CancelledError:
                pass
        
        # Close WebSocket connections
        await self.websocket_manager.close_all()
        
        # Disconnect from all exchanges
        disconnect_tasks = []
        for name, connector in self.connectors.items():
            task = asyncio.create_task(connector.disconnect())
            disconnect_tasks.append(task)
        
        await asyncio.gather(*disconnect_tasks, return_exceptions=True)
        
        self.logger.info("All connections shutdown successfully")
    
    def get_connector(self, exchange: str) -> Optional[BaseExchangeConnector]:
        """Get specific exchange connector"""
        return self.connectors.get(exchange.lower())
    
    def get_health_status(self) -> Dict[str, Any]:
        """Get overall system health status"""
        health = self.performance_monitor.get_health_status()
        
        # Add connection status
        health['connections'] = {
            name: connector.is_connected()
            for name, connector in self.connectors.items()
        }
        
        return health


# Export main classes and functions
__all__ = [
    'OrderRequest', 'OrderResponse', 'MarketData', 'OrderbookData', 'PositionData',
    'OrderSide', 'OrderType', 'TimeInForce', 'PeggedOrderParams',
    'BaseExchangeConnector', 'BinanceConnector', 'BybitConnector',
    'ExchangeConnectorFactory', 'MultiExchangeConnector', 'APIConnectorManager',
    'SecurityManager', 'RateLimiter', 'WebSocketManager', 'PerformanceMonitor'
]
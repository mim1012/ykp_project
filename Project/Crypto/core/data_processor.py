"""
Data Processor Module

Handles real-time data processing, market data aggregation,
technical indicators calculation, and data streaming management.
"""

from typing import Dict, List, Optional, Any, Callable, Union
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from enum import Enum
import asyncio
import json
import numpy as np
import pandas as pd
from collections import defaultdict, deque

from .logger import SystemLogger


class DataType(Enum):
    """Data types for processing"""
    TICKER = "ticker"
    ORDERBOOK = "orderbook"
    TRADE = "trade"
    KLINE = "kline"
    FUNDING_RATE = "funding_rate"
    OPEN_INTEREST = "open_interest"
    LIQUIDATIONS = "liquidations"
    SENTIMENT = "sentiment"
    NEWS = "news"


class TimeFrame(Enum):
    """Time frame for aggregation"""
    TICK = "tick"
    SECOND_1 = "1s"
    MINUTE_1 = "1m"
    MINUTE_5 = "5m"
    MINUTE_15 = "15m"
    MINUTE_30 = "30m"
    HOUR_1 = "1h"
    HOUR_4 = "4h"
    DAY_1 = "1d"


@dataclass
class TickerData:
    """Ticker data structure"""
    symbol: str
    price: float
    bid: float
    ask: float
    volume_24h: float
    change_24h: float
    high_24h: float
    low_24h: float
    timestamp: datetime
    exchange: str = ""


@dataclass
class TradeData:
    """Trade data structure"""
    symbol: str
    price: float
    size: float
    side: str  # 'buy' or 'sell'
    timestamp: datetime
    trade_id: str = ""
    exchange: str = ""


@dataclass
class OrderBookData:
    """Order book data structure"""
    symbol: str
    bids: List[List[float]]  # [[price, size], ...]
    asks: List[List[float]]  # [[price, size], ...]
    timestamp: datetime
    exchange: str = ""


@dataclass
class KlineData:
    """Kline/Candlestick data structure"""
    symbol: str
    timeframe: str
    open_time: datetime
    close_time: datetime
    open_price: float
    high_price: float
    low_price: float
    close_price: float
    volume: float
    trade_count: int = 0
    exchange: str = ""


@dataclass
class TechnicalIndicators:
    """Technical indicators data structure"""
    symbol: str
    timeframe: str
    sma_20: Optional[float] = None
    sma_50: Optional[float] = None
    ema_12: Optional[float] = None
    ema_26: Optional[float] = None
    rsi_14: Optional[float] = None
    macd: Optional[float] = None
    macd_signal: Optional[float] = None
    macd_histogram: Optional[float] = None
    bollinger_upper: Optional[float] = None
    bollinger_middle: Optional[float] = None
    bollinger_lower: Optional[float] = None
    atr_14: Optional[float] = None
    timestamp: datetime = field(default_factory=datetime.now)


@dataclass
class MarketSentiment:
    """Market sentiment data structure"""
    symbol: str
    fear_greed_index: Optional[float] = None
    social_sentiment: Optional[float] = None
    news_sentiment: Optional[float] = None
    funding_rate: Optional[float] = None
    open_interest: Optional[float] = None
    liquidations_24h: Optional[float] = None
    timestamp: datetime = field(default_factory=datetime.now)


class DataProcessor:
    """
    Real-time data processing engine with advanced analytics capabilities.
    
    Features:
    - Multi-exchange data aggregation
    - Real-time technical indicators
    - Market sentiment analysis
    - Data streaming and WebSocket management
    - Historical data storage and retrieval
    - Custom indicator plugins
    """
    
    def __init__(self, logger: SystemLogger, max_history: int = 10000):
        """Initialize data processor."""
        self.logger = logger
        self.max_history = max_history
        
        # Data storage
        self.ticker_data: Dict[str, deque] = defaultdict(lambda: deque(maxlen=max_history))
        self.trade_data: Dict[str, deque] = defaultdict(lambda: deque(maxlen=max_history))
        self.orderbook_data: Dict[str, OrderBookData] = {}
        self.kline_data: Dict[str, Dict[str, deque]] = defaultdict(lambda: defaultdict(lambda: deque(maxlen=1000)))
        
        # Technical indicators cache
        self.indicators_cache: Dict[str, Dict[str, TechnicalIndicators]] = defaultdict(dict)
        
        # Market sentiment data
        self.sentiment_data: Dict[str, MarketSentiment] = {}
        
        # Data subscribers
        self.subscribers: Dict[DataType, List[Callable]] = defaultdict(list)
        
        # Processing configuration
        self.enabled_indicators = ['sma', 'ema', 'rsi', 'macd', 'bollinger', 'atr']
        self.default_periods = {
            'sma': [20, 50],
            'ema': [12, 26],
            'rsi': [14],
            'atr': [14]
        }
        
        # Real-time processing flags
        self.is_processing = False
        self.processing_tasks: List[asyncio.Task] = []
        
    async def start_processing(self) -> None:
        """Start real-time data processing."""
        if self.is_processing:
            self.logger.warning("Data processor already running")
            return
            
        self.is_processing = True
        self.logger.info("Starting data processor...")
        
        # Start processing tasks
        tasks = [
            self._process_ticker_data(),
            self._process_trade_data(),
            self._calculate_indicators(),
            self._update_sentiment()
        ]
        
        self.processing_tasks = [asyncio.create_task(task) for task in tasks]
        
    async def stop_processing(self) -> None:
        """Stop real-time data processing."""
        self.is_processing = False
        self.logger.info("Stopping data processor...")
        
        # Cancel all processing tasks
        for task in self.processing_tasks:
            task.cancel()
            
        await asyncio.gather(*self.processing_tasks, return_exceptions=True)
        self.processing_tasks.clear()
        
    async def _process_ticker_data(self) -> None:
        """Process ticker data updates."""
        while self.is_processing:
            try:
                # Process ticker updates and notify subscribers
                for symbol, ticker_queue in self.ticker_data.items():
                    if ticker_queue:
                        latest_ticker = ticker_queue[-1]
                        await self._notify_subscribers(DataType.TICKER, latest_ticker)
                        
                await asyncio.sleep(1)  # Process every second
                
            except Exception as e:
                self.logger.error(f"Error processing ticker data: {e}")
                await asyncio.sleep(5)
                
    async def _process_trade_data(self) -> None:
        """Process trade data and generate aggregated metrics."""
        while self.is_processing:
            try:
                # Aggregate trade data into volume profiles, etc.
                for symbol, trades_queue in self.trade_data.items():
                    if trades_queue:
                        await self._analyze_trade_flow(symbol, list(trades_queue))
                        
                await asyncio.sleep(2)  # Process every 2 seconds
                
            except Exception as e:
                self.logger.error(f"Error processing trade data: {e}")
                await asyncio.sleep(5)
                
    async def _calculate_indicators(self) -> None:
        """Calculate technical indicators for all symbols."""
        while self.is_processing:
            try:
                for symbol in self.kline_data.keys():
                    for timeframe in self.kline_data[symbol].keys():
                        await self._update_indicators(symbol, timeframe)
                        
                await asyncio.sleep(5)  # Update every 5 seconds
                
            except Exception as e:
                self.logger.error(f"Error calculating indicators: {e}")
                await asyncio.sleep(10)
                
    async def _update_sentiment(self) -> None:
        """Update market sentiment data."""
        while self.is_processing:
            try:
                # Update sentiment for tracked symbols
                for symbol in self.sentiment_data.keys():
                    await self._calculate_sentiment(symbol)
                    
                await asyncio.sleep(60)  # Update every minute
                
            except Exception as e:
                self.logger.error(f"Error updating sentiment: {e}")
                await asyncio.sleep(30)
                
    # Data ingestion methods
    
    def add_ticker_data(self, ticker: TickerData) -> None:
        """Add ticker data point."""
        self.ticker_data[ticker.symbol].append(ticker)
        
    def add_trade_data(self, trade: TradeData) -> None:
        """Add trade data point."""
        self.trade_data[trade.symbol].append(trade)
        
    def add_orderbook_data(self, orderbook: OrderBookData) -> None:
        """Add order book data."""
        self.orderbook_data[orderbook.symbol] = orderbook
        
    def add_kline_data(self, kline: KlineData) -> None:
        """Add kline/candlestick data."""
        self.kline_data[kline.symbol][kline.timeframe].append(kline)
        
    # Technical indicators calculation
    
    async def _update_indicators(self, symbol: str, timeframe: str) -> None:
        """Update technical indicators for symbol and timeframe."""
        try:
            klines = list(self.kline_data[symbol][timeframe])
            if len(klines) < 50:  # Need minimum data points
                return
                
            # Convert to pandas DataFrame for easier calculation
            df = pd.DataFrame([{
                'open': k.open_price,
                'high': k.high_price,
                'low': k.low_price,
                'close': k.close_price,
                'volume': k.volume,
                'timestamp': k.open_time
            } for k in klines])
            
            indicators = TechnicalIndicators(symbol=symbol, timeframe=timeframe)
            
            # Simple Moving Averages
            if len(df) >= 20:
                indicators.sma_20 = df['close'].rolling(20).mean().iloc[-1]
            if len(df) >= 50:
                indicators.sma_50 = df['close'].rolling(50).mean().iloc[-1]
                
            # Exponential Moving Averages
            if len(df) >= 12:
                indicators.ema_12 = df['close'].ewm(span=12).mean().iloc[-1]
            if len(df) >= 26:
                indicators.ema_26 = df['close'].ewm(span=26).mean().iloc[-1]
                
            # RSI
            if len(df) >= 14:
                indicators.rsi_14 = self._calculate_rsi(df['close'], 14)
                
            # MACD
            if len(df) >= 26:
                macd_line, signal_line, histogram = self._calculate_macd(df['close'])
                indicators.macd = macd_line
                indicators.macd_signal = signal_line
                indicators.macd_histogram = histogram
                
            # Bollinger Bands
            if len(df) >= 20:
                bb_upper, bb_middle, bb_lower = self._calculate_bollinger_bands(df['close'], 20, 2)
                indicators.bollinger_upper = bb_upper
                indicators.bollinger_middle = bb_middle
                indicators.bollinger_lower = bb_lower
                
            # ATR
            if len(df) >= 14:
                indicators.atr_14 = self._calculate_atr(df, 14)
                
            # Cache indicators
            self.indicators_cache[symbol][timeframe] = indicators
            
        except Exception as e:
            self.logger.error(f"Error updating indicators for {symbol} {timeframe}: {e}")
            
    def _calculate_rsi(self, prices: pd.Series, period: int = 14) -> float:
        """Calculate Relative Strength Index."""
        delta = prices.diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=period).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=period).mean()
        rs = gain / loss
        rsi = 100 - (100 / (1 + rs))
        return float(rsi.iloc[-1])
        
    def _calculate_macd(self, prices: pd.Series, fast: int = 12, slow: int = 26, signal: int = 9) -> tuple:
        """Calculate MACD."""
        ema_fast = prices.ewm(span=fast).mean()
        ema_slow = prices.ewm(span=slow).mean()
        macd_line = ema_fast - ema_slow
        signal_line = macd_line.ewm(span=signal).mean()
        histogram = macd_line - signal_line
        
        return (
            float(macd_line.iloc[-1]),
            float(signal_line.iloc[-1]),
            float(histogram.iloc[-1])
        )
        
    def _calculate_bollinger_bands(self, prices: pd.Series, period: int = 20, std_dev: int = 2) -> tuple:
        """Calculate Bollinger Bands."""
        sma = prices.rolling(window=period).mean()
        std = prices.rolling(window=period).std()
        upper_band = sma + (std * std_dev)
        lower_band = sma - (std * std_dev)
        
        return (
            float(upper_band.iloc[-1]),
            float(sma.iloc[-1]),
            float(lower_band.iloc[-1])
        )
        
    def _calculate_atr(self, df: pd.DataFrame, period: int = 14) -> float:
        """Calculate Average True Range."""
        high = df['high']
        low = df['low']
        close = df['close'].shift(1)
        
        tr1 = high - low
        tr2 = abs(high - close)
        tr3 = abs(low - close)
        
        true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
        atr = true_range.rolling(window=period).mean()
        
        return float(atr.iloc[-1])
        
    # Market sentiment analysis
    
    async def _calculate_sentiment(self, symbol: str) -> None:
        """Calculate market sentiment for symbol."""
        try:
            sentiment = MarketSentiment(symbol=symbol)
            
            # Calculate funding rate sentiment
            sentiment.funding_rate = await self._get_funding_rate_sentiment(symbol)
            
            # Calculate social sentiment (placeholder)
            sentiment.social_sentiment = await self._get_social_sentiment(symbol)
            
            # Calculate news sentiment (placeholder)
            sentiment.news_sentiment = await self._get_news_sentiment(symbol)
            
            # Update sentiment data
            self.sentiment_data[symbol] = sentiment
            
            # Notify subscribers
            await self._notify_subscribers(DataType.SENTIMENT, sentiment)
            
        except Exception as e:
            self.logger.error(f"Error calculating sentiment for {symbol}: {e}")
            
    async def _get_funding_rate_sentiment(self, symbol: str) -> float:
        """Get sentiment from funding rates."""
        # Placeholder implementation
        return 0.0
        
    async def _get_social_sentiment(self, symbol: str) -> float:
        """Get sentiment from social media."""
        # Placeholder implementation
        return 0.0
        
    async def _get_news_sentiment(self, symbol: str) -> float:
        """Get sentiment from news analysis."""
        # Placeholder implementation
        return 0.0
        
    # Trade flow analysis
    
    async def _analyze_trade_flow(self, symbol: str, trades: List[TradeData]) -> None:
        """Analyze trade flow patterns."""
        try:
            if not trades:
                return
                
            recent_trades = [t for t in trades if 
                           (datetime.now() - t.timestamp).seconds < 300]  # Last 5 minutes
            
            if not recent_trades:
                return
                
            # Calculate buy/sell ratio
            buy_volume = sum(t.size for t in recent_trades if t.side == 'buy')
            sell_volume = sum(t.size for t in recent_trades if t.side == 'sell')
            
            total_volume = buy_volume + sell_volume
            if total_volume > 0:
                buy_ratio = buy_volume / total_volume
                
                # Detect unusual volume
                avg_volume = self._get_average_volume(symbol)
                if total_volume > avg_volume * 2:  # 2x average volume
                    await self._notify_unusual_activity(symbol, {
                        'type': 'volume_spike',
                        'current_volume': total_volume,
                        'average_volume': avg_volume,
                        'buy_ratio': buy_ratio
                    })
                    
        except Exception as e:
            self.logger.error(f"Error analyzing trade flow for {symbol}: {e}")
            
    def _get_average_volume(self, symbol: str) -> float:
        """Get average volume for symbol."""
        if symbol in self.ticker_data:
            tickers = list(self.ticker_data[symbol])
            if tickers:
                volumes = [t.volume_24h for t in tickers[-100:]]  # Last 100 data points
                return sum(volumes) / len(volumes) if volumes else 0.0
        return 0.0
        
    async def _notify_unusual_activity(self, symbol: str, activity_data: Dict[str, Any]) -> None:
        """Notify about unusual market activity."""
        self.logger.info(f"Unusual activity detected for {symbol}: {activity_data}")
        # Could trigger alerts or notifications here
        
    # Subscription management
    
    def subscribe(self, data_type: DataType, callback: Callable) -> None:
        """Subscribe to data type updates."""
        self.subscribers[data_type].append(callback)
        self.logger.info(f"New subscriber added for {data_type.value}")
        
    def unsubscribe(self, data_type: DataType, callback: Callable) -> None:
        """Unsubscribe from data type updates."""
        if callback in self.subscribers[data_type]:
            self.subscribers[data_type].remove(callback)
            
    async def _notify_subscribers(self, data_type: DataType, data: Any) -> None:
        """Notify all subscribers of data update."""
        for callback in self.subscribers[data_type]:
            try:
                if asyncio.iscoroutinefunction(callback):
                    await callback(data)
                else:
                    callback(data)
            except Exception as e:
                self.logger.error(f"Error notifying subscriber: {e}")
                
    # Data retrieval methods
    
    def get_latest_ticker(self, symbol: str) -> Optional[TickerData]:
        """Get latest ticker data for symbol."""
        if symbol in self.ticker_data and self.ticker_data[symbol]:
            return self.ticker_data[symbol][-1]
        return None
        
    def get_latest_trades(self, symbol: str, count: int = 100) -> List[TradeData]:
        """Get latest trades for symbol."""
        if symbol in self.trade_data:
            trades = list(self.trade_data[symbol])
            return trades[-count:] if count < len(trades) else trades
        return []
        
    def get_orderbook(self, symbol: str) -> Optional[OrderBookData]:
        """Get current order book for symbol."""
        return self.orderbook_data.get(symbol)
        
    def get_klines(self, symbol: str, timeframe: str, count: int = 100) -> List[KlineData]:
        """Get kline data for symbol and timeframe."""
        if symbol in self.kline_data and timeframe in self.kline_data[symbol]:
            klines = list(self.kline_data[symbol][timeframe])
            return klines[-count:] if count < len(klines) else klines
        return []
        
    def get_indicators(self, symbol: str, timeframe: str) -> Optional[TechnicalIndicators]:
        """Get technical indicators for symbol and timeframe."""
        return self.indicators_cache.get(symbol, {}).get(timeframe)
        
    def get_sentiment(self, symbol: str) -> Optional[MarketSentiment]:
        """Get market sentiment for symbol."""
        return self.sentiment_data.get(symbol)
        
    def get_latest_data(self) -> Dict[str, Any]:
        """Get latest data for all symbols."""
        return {
            'tickers': {symbol: queue[-1] if queue else None 
                       for symbol, queue in self.ticker_data.items()},
            'indicators': dict(self.indicators_cache),
            'sentiment': dict(self.sentiment_data),
            'orderbooks': dict(self.orderbook_data)
        }
        
    def get_processing_status(self) -> Dict[str, Any]:
        """Get data processor status."""
        return {
            'is_processing': self.is_processing,
            'active_tasks': len(self.processing_tasks),
            'tracked_symbols': {
                'tickers': len(self.ticker_data),
                'trades': len(self.trade_data),
                'klines': sum(len(timeframes) for timeframes in self.kline_data.values()),
                'indicators': sum(len(timeframes) for timeframes in self.indicators_cache.values())
            },
            'subscribers': {dt.value: len(subs) for dt, subs in self.subscribers.items()},
            'memory_usage': {
                'ticker_points': sum(len(queue) for queue in self.ticker_data.values()),
                'trade_points': sum(len(queue) for queue in self.trade_data.values()),
                'kline_points': sum(sum(len(tf_queue) for tf_queue in symbol_data.values()) 
                                  for symbol_data in self.kline_data.values())
            }
        }
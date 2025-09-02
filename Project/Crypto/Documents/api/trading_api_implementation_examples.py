"""
파이썬 기반 암호화폐 거래 시스템 구현 예제
바이낸스 및 바이비트 API를 활용한 실제 거래 로직 구현

작성자: Manus AI
작성일: 2025년 9월 1일
"""

import os
import time
import logging
import asyncio
from typing import Dict, List, Optional, Tuple
from decimal import Decimal
from datetime import datetime

# 바이낸스 SDK 임포트
from binance_common.configuration import ConfigurationRestAPI
from binance_common.constants import SPOT_REST_API_PROD_URL, SPOT_REST_API_TESTNET_URL
from binance_sdk_spot.spot import Spot
from binance_sdk_spot.rest_api.models import NewOrderSideEnum, NewOrderTypeEnum

# 바이비트 SDK 임포트
from pybit.unified_trading import HTTP as BybitHTTP

# 로깅 설정
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class BinanceTrader:
    """바이낸스 거래 클래스"""
    
    def __init__(self, api_key: str, api_secret: str, testnet: bool = True):
        """
        바이낸스 거래 클라이언트 초기화
        
        Args:
            api_key: API 키
            api_secret: API 시크릿
            testnet: 테스트넷 사용 여부
        """
        self.api_key = api_key
        self.api_secret = api_secret
        self.testnet = testnet
        
        # API 설정
        base_url = SPOT_REST_API_TESTNET_URL if testnet else SPOT_REST_API_PROD_URL
        self.configuration = ConfigurationRestAPI(
            api_key=api_key,
            api_secret=api_secret,
            base_path=base_url
        )
        
        # 클라이언트 초기화
        self.client = Spot(config_rest_api=self.configuration)
        
        logger.info(f"바이낸스 클라이언트 초기화 완료 (테스트넷: {testnet})")
    
    def get_account_info(self) -> Dict:
        """계정 정보 조회"""
        try:
            response = self.client.rest_api.account()
            data = response.data()
            logger.info("계정 정보 조회 성공")
            return data
        except Exception as e:
            logger.error(f"계정 정보 조회 실패: {e}")
            raise
    
    def get_symbol_price(self, symbol: str) -> float:
        """심볼 현재가 조회"""
        try:
            response = self.client.rest_api.ticker_price(symbol=symbol)
            data = response.data()
            price = float(data.price)
            logger.info(f"{symbol} 현재가: {price}")
            return price
        except Exception as e:
            logger.error(f"가격 조회 실패 ({symbol}): {e}")
            raise
    
    def get_orderbook(self, symbol: str, limit: int = 100) -> Dict:
        """오더북 조회"""
        try:
            response = self.client.rest_api.depth(symbol=symbol, limit=limit)
            data = response.data()
            logger.info(f"{symbol} 오더북 조회 성공")
            return data
        except Exception as e:
            logger.error(f"오더북 조회 실패 ({symbol}): {e}")
            raise
    
    def place_market_order(self, symbol: str, side: str, quantity: str) -> Dict:
        """시장가 주문"""
        try:
            side_enum = NewOrderSideEnum[side.upper()].value
            response = self.client.rest_api.new_order(
                symbol=symbol,
                side=side_enum,
                type=NewOrderTypeEnum["MARKET"].value,
                quantity=quantity
            )
            data = response.data()
            logger.info(f"시장가 주문 성공: {symbol} {side} {quantity}")
            return data
        except Exception as e:
            logger.error(f"시장가 주문 실패: {e}")
            raise
    
    def place_limit_order(self, symbol: str, side: str, quantity: str, price: str) -> Dict:
        """지정가 주문"""
        try:
            side_enum = NewOrderSideEnum[side.upper()].value
            response = self.client.rest_api.new_order(
                symbol=symbol,
                side=side_enum,
                type=NewOrderTypeEnum["LIMIT"].value,
                quantity=quantity,
                price=price,
                timeInForce="GTC"
            )
            data = response.data()
            logger.info(f"지정가 주문 성공: {symbol} {side} {quantity} @ {price}")
            return data
        except Exception as e:
            logger.error(f"지정가 주문 실패: {e}")
            raise
    
    def place_stop_loss_order(self, symbol: str, side: str, quantity: str, stop_price: str) -> Dict:
        """손절 주문"""
        try:
            side_enum = NewOrderSideEnum[side.upper()].value
            response = self.client.rest_api.new_order(
                symbol=symbol,
                side=side_enum,
                type=NewOrderTypeEnum["STOP_LOSS_LIMIT"].value,
                quantity=quantity,
                price=stop_price,
                stopPrice=stop_price,
                timeInForce="GTC"
            )
            data = response.data()
            logger.info(f"손절 주문 성공: {symbol} {side} {quantity} @ {stop_price}")
            return data
        except Exception as e:
            logger.error(f"손절 주문 실패: {e}")
            raise
    
    def cancel_order(self, symbol: str, order_id: int) -> Dict:
        """주문 취소"""
        try:
            response = self.client.rest_api.delete_order(
                symbol=symbol,
                orderId=order_id
            )
            data = response.data()
            logger.info(f"주문 취소 성공: {symbol} 주문ID {order_id}")
            return data
        except Exception as e:
            logger.error(f"주문 취소 실패: {e}")
            raise
    
    def get_open_orders(self, symbol: str = None) -> List[Dict]:
        """미체결 주문 조회"""
        try:
            response = self.client.rest_api.open_orders(symbol=symbol)
            data = response.data()
            logger.info(f"미체결 주문 조회 성공: {len(data)}개")
            return data
        except Exception as e:
            logger.error(f"미체결 주문 조회 실패: {e}")
            raise

class BybitTrader:
    """바이비트 거래 클래스"""
    
    def __init__(self, api_key: str, api_secret: str, testnet: bool = True):
        """
        바이비트 거래 클라이언트 초기화
        
        Args:
            api_key: API 키
            api_secret: API 시크릿
            testnet: 테스트넷 사용 여부
        """
        self.api_key = api_key
        self.api_secret = api_secret
        self.testnet = testnet
        
        # 클라이언트 초기화
        self.session = BybitHTTP(
            testnet=testnet,
            api_key=api_key,
            api_secret=api_secret
        )
        
        logger.info(f"바이비트 클라이언트 초기화 완료 (테스트넷: {testnet})")
    
    def get_wallet_balance(self, account_type: str = "UNIFIED") -> Dict:
        """지갑 잔고 조회"""
        try:
            result = self.session.get_wallet_balance(accountType=account_type)
            logger.info("지갑 잔고 조회 성공")
            return result
        except Exception as e:
            logger.error(f"지갑 잔고 조회 실패: {e}")
            raise
    
    def get_ticker_price(self, category: str, symbol: str) -> Dict:
        """티커 가격 조회"""
        try:
            result = self.session.get_tickers(category=category, symbol=symbol)
            logger.info(f"{symbol} 티커 조회 성공")
            return result
        except Exception as e:
            logger.error(f"티커 조회 실패 ({symbol}): {e}")
            raise
    
    def get_orderbook(self, category: str, symbol: str, limit: int = 25) -> Dict:
        """오더북 조회"""
        try:
            result = self.session.get_orderbook(
                category=category,
                symbol=symbol,
                limit=limit
            )
            logger.info(f"{symbol} 오더북 조회 성공")
            return result
        except Exception as e:
            logger.error(f"오더북 조회 실패 ({symbol}): {e}")
            raise
    
    def place_market_order(self, category: str, symbol: str, side: str, qty: str) -> Dict:
        """시장가 주문"""
        try:
            result = self.session.place_order(
                category=category,
                symbol=symbol,
                side=side,
                orderType="Market",
                qty=qty
            )
            logger.info(f"시장가 주문 성공: {symbol} {side} {qty}")
            return result
        except Exception as e:
            logger.error(f"시장가 주문 실패: {e}")
            raise
    
    def place_limit_order(self, category: str, symbol: str, side: str, qty: str, price: str) -> Dict:
        """지정가 주문"""
        try:
            result = self.session.place_order(
                category=category,
                symbol=symbol,
                side=side,
                orderType="Limit",
                qty=qty,
                price=price,
                timeInForce="GTC"
            )
            logger.info(f"지정가 주문 성공: {symbol} {side} {qty} @ {price}")
            return result
        except Exception as e:
            logger.error(f"지정가 주문 실패: {e}")
            raise
    
    def place_conditional_order(self, category: str, symbol: str, side: str, qty: str, 
                              price: str, trigger_price: str, stop_loss: str = None, 
                              take_profit: str = None) -> Dict:
        """조건부 주문 (TP/SL 포함)"""
        try:
            order_params = {
                "category": category,
                "symbol": symbol,
                "side": side,
                "orderType": "Limit",
                "qty": qty,
                "price": price,
                "triggerPrice": trigger_price
            }
            
            if stop_loss:
                order_params["stopLoss"] = stop_loss
            if take_profit:
                order_params["takeProfit"] = take_profit
            
            result = self.session.place_order(**order_params)
            logger.info(f"조건부 주문 성공: {symbol} {side} {qty}")
            return result
        except Exception as e:
            logger.error(f"조건부 주문 실패: {e}")
            raise
    
    def cancel_order(self, category: str, symbol: str, order_id: str) -> Dict:
        """주문 취소"""
        try:
            result = self.session.cancel_order(
                category=category,
                symbol=symbol,
                orderId=order_id
            )
            logger.info(f"주문 취소 성공: {symbol} 주문ID {order_id}")
            return result
        except Exception as e:
            logger.error(f"주문 취소 실패: {e}")
            raise
    
    def get_open_orders(self, category: str, symbol: str = None) -> Dict:
        """미체결 주문 조회"""
        try:
            result = self.session.get_open_orders(category=category, symbol=symbol)
            logger.info("미체결 주문 조회 성공")
            return result
        except Exception as e:
            logger.error(f"미체결 주문 조회 실패: {e}")
            raise
    
    def get_positions(self, category: str, symbol: str = None) -> Dict:
        """포지션 조회"""
        try:
            result = self.session.get_positions(category=category, symbol=symbol)
            logger.info("포지션 조회 성공")
            return result
        except Exception as e:
            logger.error(f"포지션 조회 실패: {e}")
            raise

class TradingStrategy:
    """거래 전략 구현 클래스"""
    
    def __init__(self, exchange_type: str, api_key: str, api_secret: str, testnet: bool = True):
        """
        거래 전략 초기화
        
        Args:
            exchange_type: 거래소 타입 ('binance' 또는 'bybit')
            api_key: API 키
            api_secret: API 시크릿
            testnet: 테스트넷 사용 여부
        """
        self.exchange_type = exchange_type.lower()
        
        if self.exchange_type == 'binance':
            self.trader = BinanceTrader(api_key, api_secret, testnet)
        elif self.exchange_type == 'bybit':
            self.trader = BybitTrader(api_key, api_secret, testnet)
        else:
            raise ValueError("지원하지 않는 거래소입니다. 'binance' 또는 'bybit'을 선택하세요.")
        
        self.active_orders = {}
        self.positions = {}
        
        logger.info(f"거래 전략 초기화 완료 ({exchange_type})")
    
    def calculate_position_size(self, balance: float, risk_percent: float, entry_price: float, stop_loss_price: float) -> float:
        """포지션 크기 계산 (리스크 관리)"""
        risk_amount = balance * (risk_percent / 100)
        price_diff = abs(entry_price - stop_loss_price)
        position_size = risk_amount / price_diff
        return round(position_size, 6)
    
    def check_price_channel_breakout(self, symbol: str, period: int = 20) -> Tuple[bool, str]:
        """
        Price Channel 돌파 확인
        
        Returns:
            (돌파 여부, 방향) - (True/False, 'UP'/'DOWN')
        """
        # 실제 구현에서는 캔들스틱 데이터를 가져와서 분석
        # 여기서는 예시로 간단한 로직 구현
        try:
            if self.exchange_type == 'binance':
                current_price = self.trader.get_symbol_price(symbol)
                # 실제로는 과거 데이터를 분석하여 채널 상/하단 계산
                # 예시: 임의의 채널 상/하단 설정
                upper_channel = current_price * 1.02  # 2% 위
                lower_channel = current_price * 0.98  # 2% 아래
                
                if current_price > upper_channel:
                    return True, 'UP'
                elif current_price < lower_channel:
                    return True, 'DOWN'
                else:
                    return False, 'NONE'
            
            elif self.exchange_type == 'bybit':
                ticker = self.trader.get_ticker_price('linear', symbol)
                current_price = float(ticker['result']['list'][0]['lastPrice'])
                
                # 동일한 로직 적용
                upper_channel = current_price * 1.02
                lower_channel = current_price * 0.98
                
                if current_price > upper_channel:
                    return True, 'UP'
                elif current_price < lower_channel:
                    return True, 'DOWN'
                else:
                    return False, 'NONE'
                    
        except Exception as e:
            logger.error(f"Price Channel 분석 실패: {e}")
            return False, 'NONE'
    
    def execute_entry_strategy(self, symbol: str, direction: str, quantity: str, entry_type: str = 'market'):
        """진입 전략 실행"""
        try:
            side = 'Buy' if direction == 'UP' else 'Sell'
            
            if self.exchange_type == 'binance':
                if entry_type == 'market':
                    order = self.trader.place_market_order(symbol, side, quantity)
                else:
                    # 지정가 주문의 경우 현재가 기준으로 가격 설정
                    current_price = self.trader.get_symbol_price(symbol)
                    price = str(current_price * 0.999 if side == 'Buy' else current_price * 1.001)
                    order = self.trader.place_limit_order(symbol, side, quantity, price)
                
            elif self.exchange_type == 'bybit':
                if entry_type == 'market':
                    order = self.trader.place_market_order('linear', symbol, side, quantity)
                else:
                    ticker = self.trader.get_ticker_price('linear', symbol)
                    current_price = float(ticker['result']['list'][0]['lastPrice'])
                    price = str(current_price * 0.999 if side == 'Buy' else current_price * 1.001)
                    order = self.trader.place_limit_order('linear', symbol, side, quantity, price)
            
            # 주문 정보 저장
            order_id = order.get('orderId') or order.get('result', {}).get('orderId')
            self.active_orders[order_id] = {
                'symbol': symbol,
                'side': side,
                'quantity': quantity,
                'type': 'entry',
                'timestamp': datetime.now()
            }
            
            logger.info(f"진입 주문 실행 완료: {symbol} {side} {quantity}")
            return order
            
        except Exception as e:
            logger.error(f"진입 전략 실행 실패: {e}")
            raise
    
    def set_stop_loss_take_profit(self, symbol: str, side: str, quantity: str, 
                                 entry_price: float, stop_loss_percent: float, 
                                 take_profit_percent: float):
        """손절/익절 주문 설정"""
        try:
            if side == 'Buy':
                stop_loss_price = entry_price * (1 - stop_loss_percent / 100)
                take_profit_price = entry_price * (1 + take_profit_percent / 100)
                sl_side = 'Sell'
                tp_side = 'Sell'
            else:
                stop_loss_price = entry_price * (1 + stop_loss_percent / 100)
                take_profit_price = entry_price * (1 - take_profit_percent / 100)
                sl_side = 'Buy'
                tp_side = 'Buy'
            
            if self.exchange_type == 'binance':
                # 바이낸스는 별도의 손절/익절 주문 생성
                sl_order = self.trader.place_stop_loss_order(
                    symbol, sl_side, quantity, str(stop_loss_price)
                )
                
                tp_order = self.trader.place_limit_order(
                    symbol, tp_side, quantity, str(take_profit_price)
                )
                
            elif self.exchange_type == 'bybit':
                # 바이비트는 조건부 주문으로 TP/SL 동시 설정 가능
                order = self.trader.place_conditional_order(
                    'linear', symbol, sl_side, quantity, 
                    str(stop_loss_price), str(stop_loss_price),
                    str(stop_loss_price), str(take_profit_price)
                )
            
            logger.info(f"손절/익절 설정 완료: SL={stop_loss_price:.6f}, TP={take_profit_price:.6f}")
            
        except Exception as e:
            logger.error(f"손절/익절 설정 실패: {e}")
            raise
    
    def monitor_positions(self):
        """포지션 모니터링"""
        try:
            if self.exchange_type == 'binance':
                account = self.trader.get_account_info()
                balances = account.get('balances', [])
                
                for balance in balances:
                    if float(balance['free']) > 0 or float(balance['locked']) > 0:
                        logger.info(f"잔고: {balance['asset']} - 사용가능: {balance['free']}, 잠김: {balance['locked']}")
                
            elif self.exchange_type == 'bybit':
                positions = self.trader.get_positions('linear')
                
                for position in positions.get('result', {}).get('list', []):
                    if float(position['size']) > 0:
                        logger.info(f"포지션: {position['symbol']} - 크기: {position['size']}, "
                                  f"진입가: {position['avgPrice']}, PnL: {position['unrealisedPnl']}")
            
        except Exception as e:
            logger.error(f"포지션 모니터링 실패: {e}")

def main():
    """메인 실행 함수"""
    # 환경변수에서 API 키 로드
    binance_api_key = os.getenv('BINANCE_API_KEY', 'your_binance_api_key')
    binance_api_secret = os.getenv('BINANCE_API_SECRET', 'your_binance_api_secret')
    
    bybit_api_key = os.getenv('BYBIT_API_KEY', 'your_bybit_api_key')
    bybit_api_secret = os.getenv('BYBIT_API_SECRET', 'your_bybit_api_secret')
    
    # 거래 전략 초기화 (테스트넷 사용)
    binance_strategy = TradingStrategy('binance', binance_api_key, binance_api_secret, testnet=True)
    bybit_strategy = TradingStrategy('bybit', bybit_api_key, bybit_api_secret, testnet=True)
    
    # 예시: BTCUSDT 거래
    symbol = 'BTCUSDT'
    
    try:
        # 바이낸스 예시
        logger.info("=== 바이낸스 거래 예시 ===")
        
        # Price Channel 돌파 확인
        breakout, direction = binance_strategy.check_price_channel_breakout(symbol)
        
        if breakout:
            logger.info(f"Price Channel 돌파 감지: {direction}")
            
            # 진입 전략 실행
            binance_strategy.execute_entry_strategy(symbol, direction, '0.001', 'market')
            
            # 손절/익절 설정 (예시: 2% 손절, 4% 익절)
            current_price = binance_strategy.trader.get_symbol_price(symbol)
            binance_strategy.set_stop_loss_take_profit(
                symbol, 'Buy' if direction == 'UP' else 'Sell', 
                '0.001', current_price, 2.0, 4.0
            )
        
        # 포지션 모니터링
        binance_strategy.monitor_positions()
        
        # 바이비트 예시
        logger.info("=== 바이비트 거래 예시 ===")
        
        # Price Channel 돌파 확인
        breakout, direction = bybit_strategy.check_price_channel_breakout(symbol)
        
        if breakout:
            logger.info(f"Price Channel 돌파 감지: {direction}")
            
            # 진입 전략 실행
            bybit_strategy.execute_entry_strategy(symbol, direction, '0.001', 'market')
        
        # 포지션 모니터링
        bybit_strategy.monitor_positions()
        
    except Exception as e:
        logger.error(f"거래 실행 중 오류 발생: {e}")

if __name__ == "__main__":
    main()


"""
PCS (Price Channel System) 3단계 청산 시스템 테스트

Comprehensive test suite for the 3-stage liquidation system including:
- Unit tests for all core components
- Integration tests with trading engine
- Performance benchmarks
- Edge case validation

작성자: Quantitative Crypto Trading System
작성일: 2025년 9월 1일
"""

import pytest
import asyncio
from datetime import datetime, timedelta
from unittest.mock import Mock, AsyncMock, patch
import numpy as np
from typing import Dict, List, Any

# Core imports
from core.logger import SystemLogger
from core.data_processor import KlineData, TickerData, TechnicalIndicators
from core.trading_engine import Position

# PCS System imports
from strategies.exit_strategies import (
    PCSPosition, PCSExitExecutor, TrendReversalDetector,
    PerformanceAnalyzer, PCSLiquidationStage, ExitUrgency,
    PriceChannelCalculator, ChannelBreakoutDetector,
    PriceChannel, ChannelBreakout
)


class TestPCSPosition:
    """PCS Position 클래스 테스트"""
    
    def test_pcs_position_initialization(self):
        """PCS 포지션 초기화 테스트"""
        entry_time = datetime.now()
        position = PCSPosition(
            symbol="BTCUSDT",
            side="long",
            original_size=1.0,
            entry_price=50000.0,
            entry_time=entry_time
        )
        
        assert position.symbol == "BTCUSDT"
        assert position.side == "long"
        assert position.original_size == 1.0
        assert position.remaining_size == 1.0
        assert position.entry_price == 50000.0
        assert position.current_stage == PCSLiquidationStage.STAGE_1
        assert position.stop_loss_price == 50000.0  # 초기값은 진입가
        
    def test_execute_stage1_liquidation(self):
        """1단 청산 실행 테스트"""
        position = PCSPosition(
            symbol="BTCUSDT",
            side="long",
            original_size=1.0,
            entry_price=50000.0,
            entry_time=datetime.now()
        )
        
        # 1단 청산 실행 (30% 청산)
        success = position.execute_stage_liquidation(
            PCSLiquidationStage.STAGE_1,
            0.3,  # 30% 청산
            51000.0  # 2% 수익 가격
        )
        
        assert success
        assert position.remaining_size == 0.7
        assert position.current_stage == PCSLiquidationStage.STAGE_2
        assert 'stage_1' in position.liquidated_amounts
        assert position.liquidated_amounts['stage_1'] == 0.3
        assert position.liquidation_prices['stage_1'] == 51000.0
        
        # 손익 계산 확인
        expected_pnl = (51000.0 - 50000.0) * 0.3  # 300 USDT
        assert position.stage_pnls['stage_1'] == expected_pnl
        assert position.total_pnl == expected_pnl
    
    def test_execute_all_stage_liquidations(self):
        """전체 3단계 청산 테스트"""
        position = PCSPosition(
            symbol="BTCUSDT",
            side="long", 
            original_size=1.0,
            entry_price=50000.0,
            entry_time=datetime.now()
        )
        
        # 1단 청산: 30%
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_1, 0.3, 51000.0)
        assert position.current_stage == PCSLiquidationStage.STAGE_2
        
        # 2단 청산: 잔여의 50% = 0.35
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_2, 0.35, 50800.0)
        assert position.remaining_size == 0.35
        assert position.current_stage == PCSLiquidationStage.STAGE_3
        
        # 3단 청산: 나머지 전부 
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_3, 0.35, 50600.0)
        assert position.remaining_size == 0.0
        assert position.current_stage == PCSLiquidationStage.COMPLETED
        
        # 총 손익 확인
        expected_total_pnl = (
            (51000.0 - 50000.0) * 0.3 +   # Stage 1: 300 USDT
            (50800.0 - 50000.0) * 0.35 +  # Stage 2: 280 USDT  
            (50600.0 - 50000.0) * 0.35    # Stage 3: 210 USDT
        )
        assert abs(position.total_pnl - expected_total_pnl) < 0.01
    
    def test_liquidated_percentage_calculation(self):
        """청산 비율 계산 테스트"""
        position = PCSPosition(
            symbol="BTCUSDT",
            side="long",
            original_size=2.0,
            entry_price=50000.0,
            entry_time=datetime.now()
        )
        
        # 30% 청산
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_1, 0.6, 51000.0)
        assert position.liquidated_percentage == 30.0
        
        # 추가 35% 청산 (총 65%)
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_2, 0.7, 50800.0)
        assert position.liquidated_percentage == 65.0


class TestTrendReversalDetector:
    """추세 반전 감지기 테스트"""
    
    def setup_method(self):
        """테스트 설정"""
        self.logger = Mock(spec=SystemLogger)
        self.detector = TrendReversalDetector(self.logger)
    
    def create_test_klines(self, patterns: List[str]) -> List[KlineData]:
        """테스트용 캔들 데이터 생성"""
        klines = []
        base_price = 50000.0
        
        for i, pattern in enumerate(patterns):
            time_stamp = datetime.now() - timedelta(minutes=len(patterns)-i)
            
            if pattern == "bull":  # 양봉
                open_price = base_price + i * 100
                close_price = open_price + 200
                high_price = close_price + 50
                low_price = open_price - 30
            elif pattern == "bear":  # 음봉
                open_price = base_price + i * 100
                close_price = open_price - 200
                high_price = open_price + 30
                low_price = close_price - 50
            elif pattern == "doji":  # 도지
                open_price = base_price + i * 100
                close_price = open_price + 10
                high_price = open_price + 100
                low_price = open_price - 100
            else:  # 기본
                open_price = base_price + i * 100
                close_price = open_price + 50
                high_price = close_price + 25
                low_price = open_price - 25
            
            kline = KlineData(
                symbol="BTCUSDT",
                timeframe="1m",
                open_time=time_stamp,
                close_time=time_stamp + timedelta(minutes=1),
                open_price=open_price,
                high_price=high_price,
                low_price=low_price,
                close_price=close_price,
                volume=100.0
            )
            klines.append(kline)
        
        return klines
    
    def test_detect_long_position_reversal(self):
        """롱 포지션 반전 패턴 감지 테스트"""
        # 연속 음봉 패턴 (롱 포지션에 불리)
        klines = self.create_test_klines(["bull", "bear", "bear"])
        
        is_reversal, strength = self.detector.detect_reversal_pattern(klines, "long")
        
        assert is_reversal
        assert strength > 0.0
        assert strength <= 1.0
    
    def test_detect_short_position_reversal(self):
        """숏 포지션 반전 패턴 감지 테스트"""
        # 연속 양봉 패턴 (숏 포지션에 불리)
        klines = self.create_test_klines(["bear", "bull", "bull"])
        
        is_reversal, strength = self.detector.detect_reversal_pattern(klines, "short")
        
        assert is_reversal
        assert strength > 0.0
    
    def test_no_reversal_pattern(self):
        """반전 패턴 없는 경우 테스트"""
        # 상승 추세 지속 (롱 포지션에 유리)
        klines = self.create_test_klines(["bull", "bull", "bull"])
        
        is_reversal, strength = self.detector.detect_reversal_pattern(klines, "long")
        
        assert not is_reversal
        assert strength < 0.6  # 임계값 미달
    
    def test_insufficient_data(self):
        """데이터 부족 시 테스트"""
        klines = self.create_test_klines(["bull"])  # 1개만
        
        is_reversal, strength = self.detector.detect_reversal_pattern(klines, "long")
        
        assert not is_reversal
        assert strength == 0.0


class TestPriceChannelCalculator:
    """Price Channel 계산기 테스트"""
    
    def setup_method(self):
        """테스트 설정"""
        self.logger = Mock(spec=SystemLogger)
        self.calculator = PriceChannelCalculator(self.logger, period=5)  # 짧은 기간으로 테스트
    
    def create_test_klines(self, prices: List[float]) -> List[KlineData]:
        """테스트용 캔들 데이터 생성"""
        klines = []
        
        for i, price in enumerate(prices):
            time_stamp = datetime.now() - timedelta(minutes=len(prices)-i)
            
            kline = KlineData(
                symbol="BTCUSDT",
                timeframe="1m",
                open_time=time_stamp,
                close_time=time_stamp + timedelta(minutes=1),
                open_price=price,
                high_price=price + 50,
                low_price=price - 50,
                close_price=price,
                volume=100.0
            )
            klines.append(kline)
        
        return klines
    
    def test_add_kline_data(self):
        """캔들 데이터 추가 테스트"""
        klines = self.create_test_klines([50000, 50100, 50200])
        
        for kline in klines:
            success = self.calculator.add_kline_data("BTCUSDT", kline)
            assert success
        
        # 데이터 개수 확인
        assert len(self.calculator.symbol_data["BTCUSDT"]) == 3
    
    def test_calculate_price_channel(self):
        """Price Channel 계산 테스트"""
        # 5개 가격으로 채널 계산
        prices = [49900, 50000, 50100, 50200, 50300]
        klines = self.create_test_klines(prices)
        
        for kline in klines:
            self.calculator.add_kline_data("BTCUSDT", kline)
        
        channel = self.calculator.calculate_price_channel("BTCUSDT")
        
        assert channel is not None
        assert channel.symbol == "BTCUSDT"
        assert channel.period == 5
        assert channel.upper_line == 50350  # 최고가 + 50
        assert channel.lower_line == 49850  # 최저가 - 50
        assert channel.middle_line == (50350 + 49850) / 2
        assert channel.channel_width == 500
    
    def test_insufficient_data_for_calculation(self):
        """데이터 부족 시 계산 테스트"""
        # 2개 데이터만 (최소 요구: 5개)
        prices = [50000, 50100]
        klines = self.create_test_klines(prices)
        
        for kline in klines:
            self.calculator.add_kline_data("BTCUSDT", kline)
        
        channel = self.calculator.calculate_price_channel("BTCUSDT")
        assert channel is None
    
    @pytest.mark.asyncio
    async def test_async_multiple_symbols_calculation(self):
        """비동기 다중 심볼 계산 테스트"""
        # 여러 심볼 데이터 준비
        symbols = ["BTCUSDT", "ETHUSDT", "BNBUSDT"]
        prices = [50000, 50100, 50200, 50300, 50400]
        
        for symbol in symbols:
            klines = self.create_test_klines(prices)
            for kline in klines:
                kline.symbol = symbol
                self.calculator.add_kline_data(symbol, kline)
        
        # 비동기 계산
        results = await self.calculator.calculate_multiple_symbols_async(symbols)
        
        assert len(results) == 3
        for symbol in symbols:
            assert results[symbol] is not None
            assert results[symbol].symbol == symbol
    
    def test_performance_stats(self):
        """성능 통계 테스트"""
        prices = [50000, 50100, 50200, 50300, 50400]
        klines = self.create_test_klines(prices)
        
        for kline in klines:
            self.calculator.add_kline_data("BTCUSDT", kline)
        
        # 계산 실행 (성능 메트릭 생성)
        self.calculator.calculate_price_channel("BTCUSDT")
        
        stats = self.calculator.get_performance_stats("BTCUSDT")
        
        assert 'avg_calc_time_ms' in stats
        assert 'max_calc_time_ms' in stats
        assert 'calc_count' in stats
        assert 'performance_target_met' in stats
        assert stats['calc_count'] == 1


class TestChannelBreakoutDetector:
    """채널 이탈 감지기 테스트"""
    
    def setup_method(self):
        """테스트 설정"""
        self.logger = Mock(spec=SystemLogger)
        self.detector = ChannelBreakoutDetector(self.logger, threshold_percentage=0.5)
    
    def create_test_channel(self, upper: float, lower: float) -> PriceChannel:
        """테스트용 Price Channel 생성"""
        return PriceChannel(
            symbol="BTCUSDT",
            period=20,
            upper_line=upper,
            lower_line=lower,
            middle_line=(upper + lower) / 2,
            channel_width=upper - lower,
            calculation_time=datetime.now(),
            data_points_used=20
        )
    
    def test_upper_breakout_detection(self):
        """상단선 이탈 감지 테스트"""
        channel = self.create_test_channel(50000, 49000)
        current_price = 50300  # 0.6% 이탈
        
        breakout = self.detector.detect_breakout("BTCUSDT", current_price, channel)
        
        assert breakout is not None
        assert breakout.breakout_type == "upper"
        assert breakout.breakout_percentage >= 0.5
        assert breakout.is_significant
        assert breakout.severity in ['minor', 'moderate', 'major']
    
    def test_lower_breakout_detection(self):
        """하단선 이탈 감지 테스트"""
        channel = self.create_test_channel(50000, 49000)
        current_price = 48750  # 0.51% 이탈
        
        breakout = self.detector.detect_breakout("BTCUSDT", current_price, channel)
        
        assert breakout is not None
        assert breakout.breakout_type == "lower"
        assert breakout.breakout_percentage >= 0.5
        assert breakout.is_significant
    
    def test_no_breakout(self):
        """이탈 없는 경우 테스트"""
        channel = self.create_test_channel(50000, 49000)
        current_price = 49500  # 채널 내부
        
        breakout = self.detector.detect_breakout("BTCUSDT", current_price, channel)
        
        assert breakout is None
    
    def test_insufficient_breakout(self):
        """임계값 미달 이탈 테스트"""
        channel = self.create_test_channel(50000, 49000)
        current_price = 50100  # 0.2% 이탈 (임계값 0.5% 미달)
        
        breakout = self.detector.detect_breakout("BTCUSDT", current_price, channel)
        
        assert breakout is None
    
    def test_breakout_severity_classification(self):
        """이탈 심각도 분류 테스트"""
        channel = self.create_test_channel(50000, 49000)
        
        # Minor 이탈 (0.8%)
        minor_breakout = self.detector.detect_breakout("BTCUSDT", 50400, channel)
        assert minor_breakout.severity == 'minor'
        
        # Moderate 이탈 (1.2%)
        moderate_breakout = self.detector.detect_breakout("BTCUSDT", 50600, channel)
        assert moderate_breakout.severity == 'moderate'
        
        # Major 이탈 (2.5%)
        major_breakout = self.detector.detect_breakout("BTCUSDT", 51250, channel)
        assert major_breakout.severity == 'major'
    
    def test_recent_breakouts_history(self):
        """최근 이탈 기록 테스트"""
        channel = self.create_test_channel(50000, 49000)
        
        # 여러 이탈 기록
        breakout1 = self.detector.detect_breakout("BTCUSDT", 50300, channel)
        breakout2 = self.detector.detect_breakout("BTCUSDT", 50400, channel)
        
        recent_breakouts = self.detector.get_recent_breakouts("BTCUSDT")
        
        assert len(recent_breakouts) == 2
        assert recent_breakouts[0].breakout_percentage != recent_breakouts[1].breakout_percentage
    
    def test_breakout_statistics(self):
        """이탈 통계 테스트"""
        channel = self.create_test_channel(50000, 49000)
        
        # 상단, 하단 이탈 각각 기록
        self.detector.detect_breakout("BTCUSDT", 50300, channel)  # 상단
        self.detector.detect_breakout("BTCUSDT", 48700, channel)  # 하단
        self.detector.detect_breakout("BTCUSDT", 50600, channel)  # 상단
        
        stats = self.detector.get_breakout_statistics("BTCUSDT")
        
        assert stats['total_breakouts'] == 3
        assert stats['upper_breakouts'] == 2
        assert stats['lower_breakouts'] == 1
        assert 'avg_severity' in stats
        assert 'max_breakout_percentage' in stats


class TestPCSExitExecutor:
    """PCS 청산 실행기 테스트"""
    
    def setup_method(self):
        """테스트 설정"""
        self.logger = Mock(spec=SystemLogger)
        self.mock_api_connector = Mock()
        self.executor = PCSExitExecutor(self.logger, self.mock_api_connector)
    
    def create_test_position(self) -> PCSPosition:
        """테스트용 PCS 포지션 생성"""
        return PCSPosition(
            symbol="BTCUSDT",
            side="long",
            original_size=1.0,
            entry_price=50000.0,
            entry_time=datetime.now() - timedelta(minutes=10)  # 10분 전 진입
        )
    
    def create_test_market_data(self, current_price: float, 
                              klines: List[KlineData] = None) -> Dict[str, Any]:
        """테스트용 마켓 데이터 생성"""
        return {
            'price': current_price,
            'klines': klines or [],
            'timestamp': datetime.now()
        }
    
    @pytest.mark.asyncio
    async def test_stage1_evaluation_profit_threshold(self):
        """1단 청산 조건 평가 테스트 (2% 수익)"""
        position = self.create_test_position()
        market_data = self.create_test_market_data(51000.0)  # 2% 수익
        
        exit_info = await self.executor.evaluate_exit_conditions(position, market_data)
        
        assert exit_info is not None
        assert exit_info['stage'] == PCSLiquidationStage.STAGE_1
        assert exit_info['liquidation_amount'] == 0.3  # 30% 청산
        assert exit_info['urgency'] == ExitUrgency.LOW
        assert 'move_stop_to_breakeven' in exit_info.get('post_actions', [])
    
    @pytest.mark.asyncio
    async def test_stage1_no_profit_threshold(self):
        """1단 청산 조건 미달 테스트"""
        position = self.create_test_position()
        market_data = self.create_test_market_data(50500.0)  # 1% 수익 (미달)
        
        exit_info = await self.executor.evaluate_exit_conditions(position, market_data)
        
        assert exit_info is None
    
    @pytest.mark.asyncio
    async def test_stage2_channel_breakout(self):
        """2단 청산 조건 평가 테스트 (채널 이탈)"""
        position = self.create_test_position()
        position.current_stage = PCSLiquidationStage.STAGE_2  # 이미 1단 완료
        position.remaining_size = 0.7
        
        # 기술적 지표에 Price Channel 설정
        indicators = Mock()
        indicators.price_channel = {
            'upper': 51000,
            'lower': 49000
        }
        
        # 하단선 이탈 가격 (롱 포지션에 불리)
        market_data = self.create_test_market_data(48750.0)  # 0.51% 이탈
        
        exit_info = await self.executor.evaluate_exit_conditions(
            position, market_data, indicators
        )
        
        assert exit_info is not None
        assert exit_info['stage'] == PCSLiquidationStage.STAGE_2
        assert exit_info['liquidation_amount'] == 0.35  # 잔여의 50%
        assert 'activate_trailing_stop' in exit_info.get('post_actions', [])
    
    @pytest.mark.asyncio
    async def test_stage3_trend_reversal(self):
        """3단 청산 조건 평가 테스트 (추세 반전)"""
        position = self.create_test_position()
        position.current_stage = PCSLiquidationStage.STAGE_3  # 2단까지 완료
        position.remaining_size = 0.35
        
        # 연속 음봉 패턴 캔들 데이터
        klines = []
        for i in range(3):
            time_stamp = datetime.now() - timedelta(minutes=3-i)
            kline = KlineData(
                symbol="BTCUSDT",
                timeframe="1m",
                open_time=time_stamp,
                close_time=time_stamp + timedelta(minutes=1),
                open_price=50000 + i * 50,
                high_price=50050 + i * 50,
                low_price=49800 + i * 50,  # 하락 패턴
                close_price=49850 + i * 50,
                volume=100.0
            )
            klines.append(kline)
        
        market_data = self.create_test_market_data(49500.0, klines)
        
        exit_info = await self.executor.evaluate_exit_conditions(position, market_data)
        
        assert exit_info is not None
        assert exit_info['stage'] == PCSLiquidationStage.STAGE_3
        assert exit_info['liquidation_amount'] == 0.35  # 나머지 전부
        assert exit_info['urgency'] == ExitUrgency.CRITICAL
        assert exit_info['order_type'].name == 'MARKET'  # 시장가 주문
    
    @pytest.mark.asyncio
    async def test_min_hold_time_restriction(self):
        """최소 보유 시간 제한 테스트"""
        # 1분 전에 진입한 포지션 (최소 5분 미달)
        position = PCSPosition(
            symbol="BTCUSDT",
            side="long",
            original_size=1.0,
            entry_price=50000.0,
            entry_time=datetime.now() - timedelta(minutes=1)
        )
        
        market_data = self.create_test_market_data(51000.0)  # 2% 수익
        
        exit_info = await self.executor.evaluate_exit_conditions(position, market_data)
        
        assert exit_info is None  # 최소 보유 시간 미달로 청산 안됨
    
    @pytest.mark.asyncio
    async def test_liquidation_execution_success(self):
        """청산 실행 성공 테스트"""
        position = self.create_test_position()
        
        exit_info = {
            'stage': PCSLiquidationStage.STAGE_1,
            'liquidation_amount': 0.3,
            'liquidation_price': 51000.0,
            'urgency': ExitUrgency.LOW,
            'order_type': 'LIMIT',
            'reason': '2% 수익 달성 - 30% 부분 청산',
            'post_actions': ['move_stop_to_breakeven']
        }
        
        # Mock API 응답
        self.mock_api_connector.place_order = AsyncMock(return_value={'success': True})
        
        success = await self.executor.execute_liquidation(position, exit_info)
        
        assert success
        assert position.current_stage == PCSLiquidationStage.STAGE_2
        assert position.remaining_size == 0.7
        assert position.liquidated_amounts['stage_1'] == 0.3
        assert position.stop_loss_price == position.entry_price  # 무손실 구간 설정
    
    def test_performance_metrics(self):
        """성능 메트릭 테스트"""
        # 평가 시간 기록을 위한 더미 시간 추가
        self.executor.evaluation_times.extend([1.2, 2.3, 3.1, 4.8, 2.9])
        
        metrics = self.executor.performance_metrics
        
        assert 'avg_evaluation_time_ms' in metrics
        assert 'max_evaluation_time_ms' in metrics
        assert 'evaluation_count' in metrics
        assert 'performance_target_met' in metrics
        assert metrics['evaluation_count'] == 5
        assert metrics['performance_target_met'] == True  # 모든 값이 5ms 미만


class TestPerformanceAnalyzer:
    """성능 분석기 테스트"""
    
    def setup_method(self):
        """테스트 설정"""
        self.logger = Mock(spec=SystemLogger)
        self.analyzer = PerformanceAnalyzer(self.logger)
    
    def create_completed_position(self, total_pnl: float, 
                                hold_time_minutes: int) -> PCSPosition:
        """완료된 테스트 포지션 생성"""
        position = PCSPosition(
            symbol="BTCUSDT",
            side="long",
            original_size=1.0,
            entry_price=50000.0,
            entry_time=datetime.now() - timedelta(minutes=hold_time_minutes)
        )
        
        # 3단계 청산 시뮬레이션
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_1, 0.3, 51000.0)
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_2, 0.35, 50800.0)
        position.execute_stage_liquidation(PCSLiquidationStage.STAGE_3, 0.35, 50000.0 + total_pnl)
        
        position.total_pnl = total_pnl
        position.current_stage = PCSLiquidationStage.COMPLETED
        
        return position
    
    def test_add_completed_positions(self):
        """완료된 포지션 추가 테스트"""
        # 수익 포지션
        profit_position = self.create_completed_position(500.0, 30)
        self.analyzer.add_completed_position(profit_position)
        
        # 손실 포지션
        loss_position = self.create_completed_position(-200.0, 45)
        self.analyzer.add_completed_position(loss_position)
        
        assert len(self.analyzer.position_history) == 2
        
        # 단계별 성과 기록 확인
        assert len(self.analyzer.stage_performance['stage_1']) == 2
        assert len(self.analyzer.stage_performance['stage_2']) == 2
        assert len(self.analyzer.stage_performance['stage_3']) == 2
    
    def test_performance_report_generation(self):
        """성능 보고서 생성 테스트"""
        # 여러 포지션 추가
        positions = [
            self.create_completed_position(300.0, 25),
            self.create_completed_position(150.0, 35),
            self.create_completed_position(-100.0, 20),
            self.create_completed_position(250.0, 40)
        ]
        
        for pos in positions:
            self.analyzer.add_completed_position(pos)
        
        report = self.analyzer.generate_performance_report()
        
        assert '총_포지션_수' in report
        assert report['총_포지션_수'] == 4
        
        assert '전체_수익률' in report
        assert report['전체_수익률']['total_pnl'] == 600.0  # 총 수익
        assert report['전체_수익률']['win_rate'] == 75.0  # 승률 75%
        
        assert '평균_보유시간_분' in report
        assert report['평균_보유시간_분'] == 30.0  # 평균 30분
        
        assert '단계별_성과' in report
        stage_performance = report['단계별_성과']
        
        for stage in ['stage_1', 'stage_2', 'stage_3']:
            assert stage in stage_performance
            assert 'execution_count' in stage_performance[stage]
            assert 'avg_pnl' in stage_performance[stage]
            assert 'success_rate' in stage_performance[stage]
    
    def test_optimization_suggestions(self):
        """최적화 제안 생성 테스트"""
        # 3단 청산이 많은 시나리오 (30% 이상)
        positions = []
        for i in range(10):
            pos = self.create_completed_position(100.0, 15)  # 짧은 보유 시간
            positions.append(pos)
        
        for pos in positions:
            self.analyzer.add_completed_position(pos)
        
        report = self.analyzer.generate_performance_report()
        suggestions = report['최적화_제안']
        
        assert isinstance(suggestions, list)
        assert len(suggestions) > 0
        
        # 짧은 보유 시간에 대한 제안이 포함되어야 함
        short_holding_suggestion = any(
            "빠른 청산" in suggestion or "수익 기회 상실" in suggestion
            for suggestion in suggestions
        )
        assert short_holding_suggestion
    
    def test_empty_position_history(self):
        """포지션 기록 없는 경우 테스트"""
        report = self.analyzer.generate_performance_report()
        
        assert 'message' in report
        assert '분석할 포지션 데이터가 없습니다' in report['message']


class TestIntegrationPCSSystem:
    """PCS 시스템 통합 테스트"""
    
    def setup_method(self):
        """통합 테스트 설정"""
        self.logger = Mock(spec=SystemLogger)
        
        # 모든 컴포넌트 초기화
        self.executor = PCSExitExecutor(self.logger)
        self.calculator = PriceChannelCalculator(self.logger, period=5)
        self.detector = ChannelBreakoutDetector(self.logger)
        self.analyzer = PerformanceAnalyzer(self.logger)
    
    def create_complete_market_scenario(self) -> Tuple[PCSPosition, Dict[str, Any]]:
        """완전한 시장 시나리오 생성"""
        # PCS 포지션
        position = PCSPosition(
            symbol="BTCUSDT",
            side="long",
            original_size=2.0,
            entry_price=50000.0,
            entry_time=datetime.now() - timedelta(minutes=15)
        )
        
        # 가격 상승 시나리오 캔들 데이터
        klines = []
        base_price = 49500
        for i in range(10):
            price = base_price + i * 100
            time_stamp = datetime.now() - timedelta(minutes=10-i)
            
            kline = KlineData(
                symbol="BTCUSDT",
                timeframe="1m",
                open_time=time_stamp,
                close_time=time_stamp + timedelta(minutes=1),
                open_price=price,
                high_price=price + 100,
                low_price=price - 50,
                close_price=price + 50,
                volume=1000.0
            )
            klines.append(kline)
        
        # 마켓 데이터
        market_data = {
            'price': 51000.0,  # 2% 수익
            'klines': klines,
            'timestamp': datetime.now()
        }
        
        return position, market_data
    
    @pytest.mark.asyncio
    async def test_complete_3_stage_liquidation_flow(self):
        """완전한 3단계 청산 플로우 테스트"""
        position, market_data = self.create_complete_market_scenario()
        
        # Price Channel 데이터 추가
        for kline in market_data['klines']:
            self.calculator.add_kline_data("BTCUSDT", kline)
        
        # Price Channel 계산
        channel = self.calculator.calculate_price_channel("BTCUSDT")
        assert channel is not None
        
        # 기술적 지표 설정
        indicators = Mock()
        indicators.price_channel = {
            'upper': channel.upper_line,
            'lower': channel.lower_line
        }
        
        # === 1단 청산 테스트 ===
        exit_info_1 = await self.executor.evaluate_exit_conditions(
            position, market_data, indicators
        )
        
        assert exit_info_1 is not None
        assert exit_info_1['stage'] == PCSLiquidationStage.STAGE_1
        
        # 1단 청산 실행
        success_1 = await self.executor.execute_liquidation(position, exit_info_1)
        assert success_1
        assert position.remaining_size == 1.4  # 0.6 청산됨
        assert position.current_stage == PCSLiquidationStage.STAGE_2
        
        # === 2단 청산 테스트 ===
        # 채널 하단 이탈 시나리오
        market_data_2 = market_data.copy()
        market_data_2['price'] = channel.lower_line * 0.994  # 0.6% 이탈
        
        exit_info_2 = await self.executor.evaluate_exit_conditions(
            position, market_data_2, indicators
        )
        
        assert exit_info_2 is not None
        assert exit_info_2['stage'] == PCSLiquidationStage.STAGE_2
        
        # 2단 청산 실행
        success_2 = await self.executor.execute_liquidation(position, exit_info_2)
        assert success_2
        assert position.current_stage == PCSLiquidationStage.STAGE_3
        
        # === 3단 청산 테스트 ===
        # 반전 패턴 시나리오 (연속 음봉)
        reversal_klines = []
        for i in range(3):
            price = 49000 - i * 100  # 하락 패턴
            time_stamp = datetime.now() - timedelta(minutes=3-i)
            
            kline = KlineData(
                symbol="BTCUSDT",
                timeframe="1m",
                open_time=time_stamp,
                close_time=time_stamp + timedelta(minutes=1),
                open_price=price + 100,
                high_price=price + 120,
                low_price=price - 50,
                close_price=price,  # 음봉
                volume=1500.0
            )
            reversal_klines.append(kline)
        
        market_data_3 = {
            'price': 48500.0,
            'klines': reversal_klines,
            'timestamp': datetime.now()
        }
        
        exit_info_3 = await self.executor.evaluate_exit_conditions(
            position, market_data_3, indicators
        )
        
        assert exit_info_3 is not None
        assert exit_info_3['stage'] == PCSLiquidationStage.STAGE_3
        assert exit_info_3['urgency'] == ExitUrgency.CRITICAL
        
        # 3단 청산 실행
        success_3 = await self.executor.execute_liquidation(position, exit_info_3)
        assert success_3
        assert position.current_stage == PCSLiquidationStage.COMPLETED
        assert position.remaining_size == 0.0
        
        # === 성능 분석 ===
        self.analyzer.add_completed_position(position)
        report = self.analyzer.generate_performance_report()
        
        assert report['총_포지션_수'] == 1
        assert '단계별_성과' in report
        assert len(report['단계별_성과']) == 3  # 3단계 모두 실행됨
    
    @pytest.mark.asyncio
    async def test_performance_benchmark(self):
        """성능 벤치마크 테스트 (<5ms per evaluation)"""
        position, market_data = self.create_complete_market_scenario()
        
        # Price Channel 데이터 준비
        for kline in market_data['klines']:
            self.calculator.add_kline_data("BTCUSDT", kline)
        
        channel = self.calculator.calculate_price_channel("BTCUSDT")
        
        indicators = Mock()
        indicators.price_channel = {
            'upper': channel.upper_line,
            'lower': channel.lower_line
        }
        
        # 100회 반복 성능 테스트
        start_time = datetime.now()
        
        for _ in range(100):
            await self.executor.evaluate_exit_conditions(
                position, market_data, indicators
            )
        
        end_time = datetime.now()
        total_time_ms = (end_time - start_time).total_seconds() * 1000
        avg_time_per_evaluation = total_time_ms / 100
        
        # 성능 목표: <5ms per evaluation
        assert avg_time_per_evaluation < 5.0, f"평균 평가 시간 {avg_time_per_evaluation:.2f}ms가 목표 5ms를 초과함"
        
        # 실행기의 성능 메트릭 확인
        metrics = self.executor.performance_metrics
        assert metrics['performance_target_met'] == True
    
    @pytest.mark.asyncio
    async def test_error_handling_and_resilience(self):
        """에러 처리 및 시스템 안정성 테스트"""
        position, _ = self.create_complete_market_scenario()
        
        # 잘못된 마켓 데이터
        invalid_market_data = {
            'price': None,  # 잘못된 가격
            'klines': [],
            'timestamp': None
        }
        
        # 에러가 발생해도 시스템이 중단되지 않고 None을 반환해야 함
        exit_info = await self.executor.evaluate_exit_conditions(
            position, invalid_market_data
        )
        
        assert exit_info is None
        
        # 빈 캔들 데이터로 채널 계산
        empty_channel = self.calculator.calculate_price_channel("NONEXISTENT")
        assert empty_channel is None
        
        # 잘못된 이탈 감지
        invalid_breakout = self.detector.detect_breakout("TEST", -1, None)
        assert invalid_breakout is None


if __name__ == "__main__":
    # 테스트 실행
    pytest.main([
        __file__,
        "-v",
        "--tb=short",
        "--capture=no"
    ])
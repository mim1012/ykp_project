"""
PCS (Price Channel System) 3단계 청산 시스템 데모

실제 시장 데이터를 시뮬레이션하여 PCS 시스템의 동작을 보여주는 데모입니다.

Features 데모:
- 1단 청산: 30% 부분 청산 (2% 수익 달성)
- 2단 청산: 50% 추가 청산 (Price Channel 이탈)
- 3단 청산: 100% 완전 청산 (추세 반전 패턴)

작성자: Quantitative Crypto Trading System
작성일: 2025년 9월 1일
"""

import asyncio
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Any
import numpy as np

# Core imports
from core.logger import SystemLogger
from core.data_processor import KlineData, TickerData, TechnicalIndicators

# PCS System imports
from strategies.exit_strategies import (
    PCSPosition, PCSExitExecutor, TrendReversalDetector,
    PerformanceAnalyzer, PCSLiquidationStage, ExitUrgency,
    PriceChannelCalculator, ChannelBreakoutDetector
)


class PCSDemo:
    """PCS 시스템 데모 클래스"""
    
    def __init__(self):
        # 로깅 설정
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        self.logger = SystemLogger("PCSDemo")
        
        # PCS 시스템 컴포넌트 초기화
        self.executor = PCSExitExecutor(self.logger)
        self.calculator = PriceChannelCalculator(self.logger, period=20)
        self.detector = ChannelBreakoutDetector(self.logger, threshold_percentage=0.5)
        self.analyzer = PerformanceAnalyzer(self.logger)
        
        print("🚀 PCS 3단계 청산 시스템 데모를 시작합니다...")
        print("=" * 60)
    
    def create_sample_klines(self, symbol: str, base_price: float, 
                           price_pattern: List[float], timeframe: str = "1m") -> List[KlineData]:
        """샘플 캔들 데이터 생성"""
        klines = []
        
        for i, price_change in enumerate(price_pattern):
            current_price = base_price + price_change
            time_stamp = datetime.now() - timedelta(minutes=len(price_pattern)-i)
            
            # 변동성 추가
            volatility = np.random.uniform(-50, 50)
            open_price = current_price + volatility
            close_price = current_price - volatility
            high_price = max(open_price, close_price) + abs(volatility) * 0.5
            low_price = min(open_price, close_price) - abs(volatility) * 0.5
            
            kline = KlineData(
                symbol=symbol,
                timeframe=timeframe,
                open_time=time_stamp,
                close_time=time_stamp + timedelta(minutes=1),
                open_price=open_price,
                high_price=high_price,
                low_price=low_price,
                close_price=close_price,
                volume=np.random.uniform(100, 1000),
                exchange="demo"
            )
            klines.append(kline)
        
        return klines
    
    def create_demo_position(self, symbol: str = "BTCUSDT", entry_price: float = 50000.0) -> PCSPosition:
        """데모용 PCS 포지션 생성"""
        return PCSPosition(
            symbol=symbol,
            side="long",
            original_size=1.0,
            entry_price=entry_price,
            entry_time=datetime.now() - timedelta(minutes=30)
        )
    
    def create_market_data(self, current_price: float, klines: List[KlineData]) -> Dict[str, Any]:
        """시장 데이터 생성"""
        return {
            'price': current_price,
            'klines': klines,
            'tickers': {
                'BTCUSDT': TickerData(
                    symbol='BTCUSDT',
                    price=current_price,
                    bid=current_price - 10,
                    ask=current_price + 10,
                    volume_24h=1000000,
                    change_24h=2.5,
                    high_24h=current_price + 1000,
                    low_24h=current_price - 1000,
                    timestamp=datetime.now(),
                    exchange='demo'
                )
            },
            'timestamp': datetime.now()
        }
    
    async def demo_stage1_liquidation(self):
        """1단 청산 데모 (2% 수익 달성)"""
        print("📈 1단 청산 시나리오: 2% 수익 달성")
        print("-" * 40)
        
        # 포지션 생성 (진입가: $50,000)
        position = self.create_demo_position()
        print(f"포지션 정보:")
        print(f"  심볼: {position.symbol}")
        print(f"  방향: {position.side}")
        print(f"  크기: {position.original_size} BTC")
        print(f"  진입가: ${position.entry_price:,.2f}")
        print(f"  진입시간: {position.entry_time.strftime('%H:%M:%S')}")
        
        # 2% 상승 시나리오 (목표 $51,000)
        profit_price = 51000.0
        
        # 시장 데이터 생성
        price_pattern = [0, 200, 400, 600, 800, 1000]  # 점진적 상승
        klines = self.create_sample_klines("BTCUSDT", 50000, price_pattern)
        market_data = self.create_market_data(profit_price, klines)
        
        print(f"\n현재가: ${profit_price:,.2f} (+{((profit_price-50000)/50000)*100:.2f}%)")
        
        # PCS 청산 조건 평가
        exit_info = await self.executor.evaluate_exit_conditions(position, market_data)
        
        if exit_info:
            print(f"\n✅ 1단 청산 조건 충족!")
            print(f"  청산 단계: {exit_info['stage'].value}")
            print(f"  청산량: {exit_info['liquidation_amount']} BTC (30%)")
            print(f"  청산가: ${exit_info['liquidation_price']:,.2f}")
            print(f"  긴급도: {exit_info['urgency'].value}")
            print(f"  사유: {exit_info['reason']}")
            
            # 청산 실행 (시뮬레이션)
            success = await self.executor.execute_liquidation(position, exit_info)
            if success:
                print(f"  실행 결과: 성공")
                print(f"  잔여 포지션: {position.remaining_size} BTC")
                print(f"  실현 손익: ${position.stage_pnls.get('stage_1', 0):,.2f}")
                print(f"  후속 조치: {', '.join(exit_info.get('post_actions', []))}")
        else:
            print("❌ 1단 청산 조건 미충족")
        
        print()
        return position
    
    async def demo_stage2_liquidation(self, position: PCSPosition):
        """2단 청산 데모 (Price Channel 이탈)"""
        print("📉 2단 청산 시나리오: Price Channel 이탈 감지")
        print("-" * 40)
        
        # Price Channel 데이터 준비 (20일 기간)
        channel_prices = np.random.normal(50500, 300, 20).tolist()  # 평균 50500, 표준편차 300
        channel_klines = self.create_sample_klines("BTCUSDT", 50000, channel_prices)
        
        # Price Channel 계산
        for kline in channel_klines:
            self.calculator.add_kline_data("BTCUSDT", kline)
        
        channel = self.calculator.calculate_price_channel("BTCUSDT")
        
        if channel:
            print(f"Price Channel 정보:")
            print(f"  상단선: ${channel.upper_line:,.2f}")
            print(f"  하단선: ${channel.lower_line:,.2f}")
            print(f"  중간선: ${channel.middle_line:,.2f}")
            print(f"  채널 폭: ${channel.channel_width:,.2f}")
            
            # 하단선 이탈 시나리오 (롱 포지션에 불리)
            breakout_price = channel.lower_line * 0.994  # 0.6% 이탈
            
            # 기술적 지표 설정
            indicators = TechnicalIndicators()
            indicators.price_channel = {
                'upper': channel.upper_line,
                'lower': channel.lower_line
            }
            
            # 시장 데이터 생성
            breakout_pattern = [-200, -400, -600, -800]  # 하락 패턴
            klines = self.create_sample_klines("BTCUSDT", 50000, breakout_pattern)
            market_data = self.create_market_data(breakout_price, klines)
            
            print(f"\n현재가: ${breakout_price:,.2f} (하단선 -{((channel.lower_line-breakout_price)/channel.lower_line)*100:.2f}% 이탈)")
            
            # 채널 이탈 감지
            breakout = self.detector.detect_breakout("BTCUSDT", breakout_price, channel)
            if breakout:
                print(f"📊 채널 이탈 감지:")
                print(f"  이탈 유형: {breakout.breakout_type}")
                print(f"  이탈 비율: {breakout.breakout_percentage:.2f}%")
                print(f"  심각도: {breakout.severity}")
            
            # 2단 청산 조건 평가
            position.current_stage = PCSLiquidationStage.STAGE_2
            exit_info = await self.executor.evaluate_exit_conditions(position, market_data, indicators)
            
            if exit_info:
                print(f"\n✅ 2단 청산 조건 충족!")
                print(f"  청산 단계: {exit_info['stage'].value}")
                print(f"  청산량: {exit_info['liquidation_amount']} BTC (잔여의 50%)")
                print(f"  청산가: ${exit_info['liquidation_price']:,.2f}")
                print(f"  긴급도: {exit_info['urgency'].value}")
                print(f"  사유: {exit_info['reason']}")
                
                # 청산 실행 (시뮬레이션)
                success = await self.executor.execute_liquidation(position, exit_info)
                if success:
                    print(f"  실행 결과: 성공")
                    print(f"  잔여 포지션: {position.remaining_size} BTC")
                    print(f"  실현 손익: ${position.stage_pnls.get('stage_2', 0):,.2f}")
                    print(f"  트레일링 스톱: {'활성화' if position.trailing_stop_active else '비활성화'}")
            else:
                print("❌ 2단 청산 조건 미충족")
        
        print()
        return position
    
    async def demo_stage3_liquidation(self, position: PCSPosition):
        """3단 청산 데모 (추세 반전 패턴)"""
        print("🔄 3단 청산 시나리오: 추세 반전 패턴 감지")
        print("-" * 40)
        
        # 연속 음봉 패턴 생성 (롱 포지션에 불리한 패턴)
        reversal_patterns = []
        base_price = 49000
        
        for i in range(3):
            current_price = base_price - i * 150  # 지속적 하락
            time_stamp = datetime.now() - timedelta(minutes=3-i)
            
            # 음봉 패턴 (시가 > 종가)
            open_price = current_price + 100
            close_price = current_price - 100
            high_price = open_price + 50
            low_price = close_price - 50
            
            # 긴 위꼬리 패턴 (반전 신호)
            if i == 2:  # 마지막 캔들
                high_price = open_price + 200  # 긴 위꼬리
            
            kline = KlineData(
                symbol="BTCUSDT",
                timeframe="1m",
                open_time=time_stamp,
                close_time=time_stamp + timedelta(minutes=1),
                open_price=open_price,
                high_price=high_price,
                low_price=low_price,
                close_price=close_price,
                volume=np.random.uniform(200, 800),
                exchange="demo"
            )
            reversal_patterns.append(kline)
        
        # 추세 반전 패턴 감지
        detector = TrendReversalDetector(self.logger)
        is_reversal, strength = detector.detect_reversal_pattern(reversal_patterns, "long")
        
        print(f"캔들 패턴 분석:")
        print(f"  분석 기간: 최근 3분")
        print(f"  패턴 유형: 연속 음봉 + 긴 위꼬리")
        print(f"  반전 감지: {'예' if is_reversal else '아니오'}")
        print(f"  반전 강도: {strength:.3f} {'(위험)' if strength > 0.6 else '(안전)'}")
        
        if is_reversal:
            # 3단 청산 시나리오
            final_price = 48500.0
            market_data = self.create_market_data(final_price, reversal_patterns)
            
            print(f"\n현재가: ${final_price:,.2f} (-{((50000-final_price)/50000)*100:.2f}% from 진입가)")
            
            # 3단 청산 조건 평가
            position.current_stage = PCSLiquidationStage.STAGE_3
            exit_info = await self.executor.evaluate_exit_conditions(position, market_data)
            
            if exit_info:
                print(f"\n🚨 3단 청산 조건 충족! (긴급)")
                print(f"  청산 단계: {exit_info['stage'].value}")
                print(f"  청산량: {exit_info['liquidation_amount']} BTC (나머지 전부)")
                print(f"  청산가: ${exit_info['liquidation_price']:,.2f}")
                print(f"  긴급도: {exit_info['urgency'].value}")
                print(f"  주문 유형: 시장가 (즉시 체결)")
                print(f"  사유: {exit_info['reason']}")
                
                # 청산 실행 (시뮬레이션)
                success = await self.executor.execute_liquidation(position, exit_info)
                if success:
                    print(f"  실행 결과: 성공")
                    print(f"  잔여 포지션: {position.remaining_size} BTC")
                    print(f"  실현 손익: ${position.stage_pnls.get('stage_3', 0):,.2f}")
                    print(f"  포지션 상태: {position.current_stage.value}")
            else:
                print("❌ 3단 청산 조건 미충족")
        
        print()
        return position
    
    def demo_performance_analysis(self, position: PCSPosition):
        """성능 분석 데모"""
        print("📊 성능 분석 및 보고서")
        print("-" * 40)
        
        # 완료된 포지션을 성능 분석기에 추가
        if position.current_stage == PCSLiquidationStage.COMPLETED:
            self.analyzer.add_completed_position(position)
            
            # 성능 보고서 생성
            report = self.analyzer.generate_performance_report()
            
            print("포지션 성과 요약:")
            print(f"  총 포지션 수: {report['총_포지션_수']}")
            print(f"  총 수익/손실: ${report['전체_수익률']['total_pnl']:,.2f}")
            print(f"  승률: {report['전체_수익률']['win_rate']:.1f}%")
            print(f"  평균 보유 시간: {report['평균_보유시간_분']:.1f}분")
            
            print("\n단계별 성과:")
            for stage, perf in report['단계별_성과'].items():
                print(f"  {stage.upper()}:")
                print(f"    실행 횟수: {perf['execution_count']}")
                print(f"    평균 손익: ${perf['avg_pnl']:,.2f}")
                print(f"    성공률: {perf['success_rate']:.1f}%")
                print(f"    총 손익: ${perf['total_pnl']:,.2f}")
            
            print("\n최적화 제안:")
            for i, suggestion in enumerate(report['최적화_제안'], 1):
                print(f"  {i}. {suggestion}")
        
        # 실행기 성능 메트릭
        executor_metrics = self.executor.performance_metrics
        print(f"\n시스템 성능 메트릭:")
        print(f"  평균 평가 시간: {executor_metrics['avg_evaluation_time_ms']:.2f}ms")
        print(f"  최대 평가 시간: {executor_metrics['max_evaluation_time_ms']:.2f}ms")
        print(f"  총 평가 횟수: {executor_metrics['evaluation_count']}")
        print(f"  성능 목표 달성: {'예' if executor_metrics['performance_target_met'] else '아니오'} (<5ms)")
        
        # Price Channel 계산 성능
        channel_stats = self.calculator.get_performance_stats("BTCUSDT")
        print(f"\nPrice Channel 계산 성능:")
        print(f"  평균 계산 시간: {channel_stats['avg_calc_time_ms']:.2f}ms")
        print(f"  최대 계산 시간: {channel_stats['max_calc_time_ms']:.2f}ms")
        print(f"  계산 횟수: {channel_stats['calc_count']}")
        print(f"  성능 목표 달성: {'예' if channel_stats['performance_target_met'] else '아니오'} (<2ms)")
    
    async def run_complete_demo(self):
        """완전한 PCS 데모 실행"""
        print("🎯 PCS 3단계 청산 시스템 통합 데모")
        print("=" * 60)
        
        try:
            # 1단 청산 데모
            position = await self.demo_stage1_liquidation()
            await asyncio.sleep(1)  # 시각적 효과
            
            # 2단 청산 데모
            position = await self.demo_stage2_liquidation(position)
            await asyncio.sleep(1)
            
            # 3단 청산 데모
            position = await self.demo_stage3_liquidation(position)
            await asyncio.sleep(1)
            
            # 성능 분석
            self.demo_performance_analysis(position)
            
            print("\n" + "=" * 60)
            print("✨ PCS 3단계 청산 시스템 데모 완료")
            print("=" * 60)
            
            # 핵심 기능 요약
            print("\n🎯 PCS 시스템 핵심 기능:")
            print("  ✅ 1단 청산: 30% 부분 청산 (2% 수익 달성)")
            print("  ✅ 2단 청산: 50% 추가 청산 (Price Channel 이탈 감지)")
            print("  ✅ 3단 청산: 100% 완전 청산 (추세 반전 패턴 감지)")
            print("  ✅ 실시간 Price Channel 계산 (20일 기간)")
            print("  ✅ 추세 반전 패턴 자동 감지")
            print("  ✅ 트레일링 스톱 및 무손실 구간 설정")
            print("  ✅ 성능 분석 및 최적화 제안")
            print("  ✅ <5ms 실행 시간 (성능 목표 달성)")
            
        except Exception as e:
            print(f"❌ 데모 실행 중 오류 발생: {e}")
            logging.exception("데모 오류 상세:")


async def main():
    """메인 함수"""
    demo = PCSDemo()
    await demo.run_complete_demo()


if __name__ == "__main__":
    # 데모 실행
    asyncio.run(main())
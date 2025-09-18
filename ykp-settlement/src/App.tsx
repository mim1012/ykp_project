import React, { useEffect } from 'react';
import { Header } from './components/Header';
import { SettlementGrid } from './components/SettlementGrid';
import { StatusBar } from './components/StatusBar';
import { useSettlementStore } from './store/useSettlementStore';

function App() {
  const { load, setProfiles } = useSettlementStore();
  
  useEffect(() => {
    // 초기 데이터 로드
    load();
    
    // 프로파일 로드 (임시 데이터)
    setProfiles([
      {
        branchId: 'yongsan',
        dealerId: 'yongsan_01',
        dealerName: '용산점',
        simFee: 5500,
        mnpDiscount: -8000,
        documentCash: 0,
        taxRate: 0.133,
        defaultCarrier: 'SKT',
        defaultType: '신규',
      },
      {
        branchId: 'gangnam',
        dealerId: 'gangnam_01',
        dealerName: '강남점',
        simFee: 5500,
        mnpDiscount: -8000,
        documentCash: 10000,
        taxRate: 0.133,
        defaultCarrier: 'KT',
        defaultType: 'MNP',
      },
      {
        branchId: 'hongdae',
        dealerId: 'hongdae_01',
        dealerName: '홍대점',
        simFee: 5500,
        mnpDiscount: -10000,
        documentCash: 5000,
        taxRate: 0.133,
        defaultCarrier: 'LGU+',
        defaultType: '신규',
      },
    ]);
  }, [load, setProfiles]);
  
  // 키보드 단축키 (F1 - 도움말)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'F1') {
        e.preventDefault();
        // Header 컴포넌트에서 처리
      }
    };
    
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);
  
  return (
    <div className="h-screen flex flex-col bg-gray-50">
      <Header />
      
      <main className="flex-1 p-4 pb-12 overflow-hidden">
        <div className="h-full bg-white rounded-lg shadow-sm border">
          <SettlementGrid />
        </div>
      </main>
      
      <StatusBar />
    </div>
  );
}

export default App;
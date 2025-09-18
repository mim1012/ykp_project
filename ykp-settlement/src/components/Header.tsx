import React, { useState } from 'react';
import { useSettlementStore } from '../store/useSettlementStore';
import { calculateTotals } from '../utils/calculations';
import { formatKRW } from '../utils/formatters';
import { Keyboard, Plus, Save, Upload, Download, Home, HelpCircle } from 'lucide-react';
import ShortcutModal from './ShortcutModal';

export const Header: React.FC = () => {
  const { rows, addRow, save, validateAll, isSaving } = useSettlementStore();
  const [showShortcuts, setShowShortcuts] = useState(false);
  
  const totals = calculateTotals(rows);
  
  const handleSave = async () => {
    if (validateAll()) {
      await save();
      alert('저장되었습니다!');
    } else {
      alert('필수 항목을 확인해주세요.');
    }
  };
  
  return (
    <>
      <header className="bg-white border-b">
        {/* 상단 툴바 */}
        <div className="px-6 py-3 flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold">YKP 정산 시스템</h1>
            <p className="text-sm text-gray-500 mt-1">
              입력 가능: 🟨 노란색 | 자동 계산: 🔒 회색
            </p>
          </div>
          
          <div className="flex gap-2">
            <button
              onClick={() => setShowShortcuts(true)}
              className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center gap-2 text-sm"
            >
              <Keyboard size={16} />
              단축키 (F1)
            </button>
            
            <button
              onClick={addRow}
              className="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 text-sm"
            >
              <Plus size={16} />
              새 행
            </button>
            
            <button
              onClick={handleSave}
              disabled={isSaving}
              className="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400 flex items-center gap-2 text-sm"
            >
              <Save size={16} />
              {isSaving ? '저장 중...' : '저장'}
            </button>
            
            <button className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center gap-2 text-sm">
              <Upload size={16} />
              엑셀 업로드
            </button>
            
            <button className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center gap-2 text-sm">
              <Download size={16} />
              엑셀 다운로드
            </button>
          </div>
        </div>
        
        {/* KPI 카드 */}
        <div className="px-6 py-3 bg-gray-50 border-t">
          <div className="grid grid-cols-6 gap-4">
            <div className="bg-white rounded-lg p-3 border">
              <div className="text-xs text-gray-500">총 건수</div>
              <div className="text-xl font-bold">{totals.count}</div>
            </div>
            
            <div className="bg-white rounded-lg p-3 border">
              <div className="text-xs text-gray-500">리베총계</div>
              <div className="text-xl font-bold text-blue-600">
                {formatKRW(totals.totalRebate)}
              </div>
            </div>
            
            <div className="bg-white rounded-lg p-3 border">
              <div className="text-xs text-gray-500">정산금</div>
              <div className="text-xl font-bold text-green-600">
                {formatKRW(totals.settlementAmount)}
              </div>
            </div>
            
            <div className="bg-white rounded-lg p-3 border">
              <div className="text-xs text-gray-500">부가세</div>
              <div className="text-xl font-bold text-red-600">
                {formatKRW(totals.tax)}
              </div>
            </div>
            
            <div className="bg-white rounded-lg p-3 border">
              <div className="text-xs text-gray-500">평균 마진</div>
              <div className="text-xl font-bold">
                {formatKRW(totals.avgMargin)}
              </div>
            </div>
            
            <div className="bg-white rounded-lg p-3 border">
              <div className="text-xs text-gray-500">총 마진</div>
              <div className="text-xl font-bold text-purple-600">
                {formatKRW(totals.marginAfterTax)}
              </div>
            </div>
          </div>
        </div>
      </header>
      
      <ShortcutModal isOpen={showShortcuts} onClose={() => setShowShortcuts(false)} />
    </>
  );
};
import React from 'react';
import { useSettlementStore } from '../store/useSettlementStore';

export const StatusBar: React.FC = () => {
  const { rows, errors, canUndo, canRedo } = useSettlementStore();
  
  const errorCount = Object.keys(errors).reduce((count, rowId) => {
    return count + Object.keys(errors[rowId]).length;
  }, 0);
  
  return (
    <div className="fixed bottom-0 left-0 right-0 bg-white border-t px-6 py-2 flex items-center justify-between text-sm">
      <div className="flex items-center gap-4">
        <span className="text-gray-600">
          {rows.length} 행
        </span>
        
        {errorCount > 0 && (
          <span className="text-red-600 font-medium">
            ⚠️ {errorCount}개 오류
          </span>
        )}
        
        {canUndo() && (
          <span className="text-blue-600">
            실행취소 가능 (Ctrl+Z)
          </span>
        )}
        
        {canRedo() && (
          <span className="text-blue-600">
            다시실행 가능 (Ctrl+Y)
          </span>
        )}
      </div>
      
      <div className="flex items-center gap-4 text-gray-500">
        <span>Ctrl+D: 위 복사</span>
        <span>Ctrl+R: 왼쪽 복사</span>
        <span>F1: 도움말</span>
        <span>Delete: 선택 삭제</span>
      </div>
    </div>
  );
};
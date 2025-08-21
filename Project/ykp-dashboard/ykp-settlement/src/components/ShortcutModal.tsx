import React from 'react';
import { X } from 'lucide-react';

interface ShortcutModalProps {
  isOpen: boolean;
  onClose: () => void;
}

const ShortcutModal: React.FC<ShortcutModalProps> = ({ isOpen, onClose }) => {
  if (!isOpen) return null;
  
  const shortcuts = [
    { category: '이동', items: [
      { keys: '방향키', desc: '셀 이동' },
      { keys: 'Tab', desc: '다음 셀로 이동' },
      { keys: 'Shift + Tab', desc: '이전 셀로 이동' },
      { keys: 'Enter', desc: '아래 셀로 이동' },
      { keys: 'Shift + Enter', desc: '위 셀로 이동' },
    ]},
    { category: '편집', items: [
      { keys: 'F2', desc: '셀 편집 모드' },
      { keys: 'Delete', desc: '선택 영역 삭제' },
      { keys: 'Ctrl + Z', desc: '실행취소' },
      { keys: 'Ctrl + Y', desc: '다시실행' },
      { keys: 'Alt + Enter', desc: '셀 내 줄바꿈' },
    ]},
    { category: '복사', items: [
      { keys: 'Ctrl + C', desc: '복사' },
      { keys: 'Ctrl + V', desc: '붙여넣기' },
      { keys: 'Ctrl + D', desc: '위 셀 값 복사' },
      { keys: 'Ctrl + R', desc: '왼쪽 셀 값 복사' },
    ]},
    { category: '선택', items: [
      { keys: 'Ctrl + Space', desc: '열 전체 선택' },
      { keys: 'Shift + Space', desc: '행 전체 선택' },
      { keys: 'Ctrl + A', desc: '전체 선택' },
      { keys: 'Ctrl + Shift + 방향키', desc: '범위 선택 확장' },
    ]},
    { category: '기타', items: [
      { keys: 'Ctrl + F', desc: '찾기' },
      { keys: 'Ctrl + H', desc: '찾기 및 바꾸기' },
      { keys: 'Alt + =', desc: '자동 합계' },
      { keys: 'F1', desc: '도움말' },
      { keys: 'Escape', desc: '선택 취소' },
    ]},
  ];
  
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg w-[800px] max-h-[80vh] overflow-hidden">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-xl font-bold">키보드 단축키</h2>
          <button
            onClick={onClose}
            className="p-1 hover:bg-gray-100 rounded-lg"
          >
            <X size={20} />
          </button>
        </div>
        
        <div className="p-4 overflow-y-auto max-h-[calc(80vh-80px)]">
          <div className="grid grid-cols-2 gap-6">
            {shortcuts.map((category) => (
              <div key={category.category}>
                <h3 className="font-semibold text-gray-700 mb-3">{category.category}</h3>
                <div className="space-y-2">
                  {category.items.map((item, index) => (
                    <div key={index} className="flex justify-between items-center py-1">
                      <kbd className="px-2 py-1 bg-gray-100 rounded text-sm font-mono">
                        {item.keys}
                      </kbd>
                      <span className="text-sm text-gray-600">{item.desc}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default ShortcutModal;
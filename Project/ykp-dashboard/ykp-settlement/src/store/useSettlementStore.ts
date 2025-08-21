import { create } from 'zustand';
import { SettlementRow, DealerProfile } from '../types';
import { calculateSettlement } from '../utils/calculations';
import { validateRow } from '../utils/validation';

interface SettlementStore {
  // 데이터
  rows: SettlementRow[];
  profiles: DealerProfile[];
  selectedProfile: DealerProfile | null;
  
  // 상태
  isLoading: boolean;
  isSaving: boolean;
  errors: Record<string, Record<string, string>>; // rowId -> field -> error
  
  // 히스토리 (실행취소/다시실행)
  history: SettlementRow[][];
  historyIndex: number;
  
  // 액션 - 데이터
  setRows: (rows: SettlementRow[]) => void;
  addRow: () => void;
  updateRow: (id: string, updates: Partial<SettlementRow>) => void;
  deleteRow: (id: string) => void;
  
  // 액션 - 프로파일
  setProfiles: (profiles: DealerProfile[]) => void;
  selectProfile: (profile: DealerProfile) => void;
  applyProfileToRow: (rowId: string) => void;
  
  // 액션 - 검증
  validateAll: () => boolean;
  clearErrors: () => void;
  
  // 액션 - 히스토리
  undo: () => void;
  redo: () => void;
  canUndo: () => boolean;
  canRedo: () => boolean;
  
  // 액션 - 저장/로드
  save: () => Promise<void>;
  load: () => Promise<void>;
}

// 기본 프로파일
const DEFAULT_PROFILE: DealerProfile = {
  branchId: 'default',
  dealerId: 'default',
  dealerName: '기본',
  simFee: 5500,
  mnpDiscount: -8000,
  documentCash: 0,
  taxRate: 0.133,
  defaultCarrier: 'SKT',
  defaultType: '신규',
};

// 새 행 생성
const createNewRow = (profile: DealerProfile | null = null): SettlementRow => {
  const p = profile || DEFAULT_PROFILE;
  return {
    id: `row_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
    seller: '',
    dealer: p.dealerName,
    carrier: p.defaultCarrier,
    activationType: p.defaultType,
    modelName: '',
    activationDate: new Date().toISOString().split('T')[0],
    customerName: '',
    priceSettling: 0,
    verbal1: 0,
    verbal2: 0,
    gradeAmount: 0,
    additionalAmount: 0,
    cashReceived: 0,
    payback: 0,
    memo: '',
    simFee: p.simFee,
    mnpDiscount: p.mnpDiscount,
    documentCash: p.documentCash,
    taxRate: p.taxRate,
  };
};

export const useSettlementStore = create<SettlementStore>((set, get) => ({
  // 초기 상태
  rows: [],
  profiles: [DEFAULT_PROFILE],
  selectedProfile: DEFAULT_PROFILE,
  isLoading: false,
  isSaving: false,
  errors: {},
  history: [],
  historyIndex: -1,
  
  // 데이터 액션
  setRows: (rows) => {
    const calculated = rows.map(row => calculateSettlement(row));
    set((state) => ({
      rows: calculated,
      history: [...state.history.slice(0, state.historyIndex + 1), calculated],
      historyIndex: state.historyIndex + 1,
    }));
  },
  
  addRow: () => {
    const { rows, selectedProfile, history, historyIndex } = get();
    const newRow = createNewRow(selectedProfile);
    const calculated = calculateSettlement(newRow);
    const newRows = [...rows, calculated];
    
    set({
      rows: newRows,
      history: [...history.slice(0, historyIndex + 1), newRows],
      historyIndex: historyIndex + 1,
    });
  },
  
  updateRow: (id, updates) => {
    const { rows, history, historyIndex } = get();
    const newRows = rows.map(row => {
      if (row.id === id) {
        const updated = { ...row, ...updates };
        return calculateSettlement(updated);
      }
      return row;
    });
    
    // 검증
    const updatedRow = newRows.find(r => r.id === id);
    if (updatedRow) {
      const rowErrors = validateRow(updatedRow);
      set((state) => ({
        errors: {
          ...state.errors,
          [id]: rowErrors,
        },
      }));
    }
    
    set({
      rows: newRows,
      history: [...history.slice(0, historyIndex + 1), newRows],
      historyIndex: historyIndex + 1,
    });
  },
  
  deleteRow: (id) => {
    const { rows, history, historyIndex } = get();
    const newRows = rows.filter(row => row.id !== id);
    
    set({
      rows: newRows,
      history: [...history.slice(0, historyIndex + 1), newRows],
      historyIndex: historyIndex + 1,
    });
  },
  
  // 프로파일 액션
  setProfiles: (profiles) => set({ profiles }),
  
  selectProfile: (profile) => set({ selectedProfile: profile }),
  
  applyProfileToRow: (rowId) => {
    const { rows, selectedProfile } = get();
    if (!selectedProfile) return;
    
    const newRows = rows.map(row => {
      if (row.id === rowId) {
        return calculateSettlement({
          ...row,
          dealer: selectedProfile.dealerName,
          simFee: selectedProfile.simFee,
          mnpDiscount: selectedProfile.mnpDiscount,
          documentCash: selectedProfile.documentCash,
          taxRate: selectedProfile.taxRate,
        });
      }
      return row;
    });
    
    set({ rows: newRows });
  },
  
  // 검증 액션
  validateAll: () => {
    const { rows } = get();
    const allErrors: Record<string, Record<string, string>> = {};
    let hasErrors = false;
    
    rows.forEach(row => {
      const rowErrors = validateRow(row);
      if (Object.keys(rowErrors).length > 0) {
        allErrors[row.id] = rowErrors;
        hasErrors = true;
      }
    });
    
    set({ errors: allErrors });
    return !hasErrors;
  },
  
  clearErrors: () => set({ errors: {} }),
  
  // 히스토리 액션
  undo: () => {
    const { history, historyIndex } = get();
    if (historyIndex > 0) {
      set({
        rows: history[historyIndex - 1],
        historyIndex: historyIndex - 1,
      });
    }
  },
  
  redo: () => {
    const { history, historyIndex } = get();
    if (historyIndex < history.length - 1) {
      set({
        rows: history[historyIndex + 1],
        historyIndex: historyIndex + 1,
      });
    }
  },
  
  canUndo: () => get().historyIndex > 0,
  canRedo: () => get().historyIndex < get().history.length - 1,
  
  // 저장/로드 액션
  save: async () => {
    const { rows, validateAll } = get();
    
    if (!validateAll()) {
      throw new Error('검증 실패: 필수 항목을 확인하세요');
    }
    
    set({ isSaving: true });
    
    try {
      // API 호출 시뮬레이션
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // localStorage에 저장
      localStorage.setItem('settlement_data', JSON.stringify(rows));
      
      console.log('저장 완료:', rows);
    } finally {
      set({ isSaving: false });
    }
  },
  
  load: async () => {
    set({ isLoading: true });
    
    try {
      // API 호출 시뮬레이션
      await new Promise(resolve => setTimeout(resolve, 500));
      
      // localStorage에서 로드
      const saved = localStorage.getItem('settlement_data');
      if (saved) {
        const rows = JSON.parse(saved);
        get().setRows(rows);
      } else {
        // 초기 데이터
        get().setRows([createNewRow(DEFAULT_PROFILE)]);
      }
    } finally {
      set({ isLoading: false });
    }
  },
}));
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
      // YKP Settlement 데이터를 Sales API 형식으로 변환
      const salesData = rows.map(row => ({
        dealer_code: row.dealerId || 'DEFAULT',
        store_id: 1, // 기본값
        branch_id: 1, // 기본값
        sale_date: row.activationDate || new Date().toISOString().split('T')[0],
        carrier: row.carrier || 'SK',
        activation_type: row.activationType || '신규',
        model_name: row.modelName || '',
        base_price: parseFloat(row.faceValue) || 0,
        verbal1: parseFloat(row.verbal1) || 0,
        verbal2: parseFloat(row.verbal2) || 0,
        grade_amount: parseFloat(row.gradeAmount) || 0,
        additional_amount: parseFloat(row.additionalAmount) || 0,
        settlement_amount: parseFloat(row.settlementAmount) || 0,
        tax: parseFloat(row.tax) || 0,
        margin_after_tax: parseFloat(row.finalMargin) || 0,
        cash_received: parseFloat(row.cashReceived) || 0,
        payback: parseFloat(row.payback) || 0,
        phone_number: row.customerPhone || '',
        memo: row.memo || ''
      }));

      // Laravel Sales API로 저장
      const response = await fetch('http://localhost:8000/api/sales/bulk-save', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ sales: salesData })
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(`API 저장 실패: ${errorData.message || response.statusText}`);
      }

      const result = await response.json();
      
      if (!result.success) {
        throw new Error(`저장 실패: ${result.message || '알 수 없는 오류'}`);
      }

      // 성공 시 localStorage도 백업용으로 저장
      localStorage.setItem('settlement_data', JSON.stringify(rows));
      localStorage.setItem('last_save_time', new Date().toISOString());
      
      console.log('✅ 데이터베이스 저장 완료:', result);
      
      // 저장 성공 알림
      if (typeof window !== 'undefined') {
        const notification = document.createElement('div');
        notification.innerHTML = '✅ 데이터가 성공적으로 저장되었습니다!';
        notification.style.cssText = 'position:fixed;top:20px;right:20px;background:#10b981;color:white;padding:12px 20px;border-radius:8px;z-index:1000;font-weight:600;';
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
      }
      
    } catch (error) {
      console.error('저장 오류:', error);
      
      // 실패 시 localStorage라도 저장
      localStorage.setItem('settlement_data', JSON.stringify(rows));
      localStorage.setItem('last_save_error', error.message);
      
      // 오류 알림
      if (typeof window !== 'undefined') {
        const notification = document.createElement('div');
        notification.innerHTML = `❌ 저장 실패: ${error.message}`;
        notification.style.cssText = 'position:fixed;top:20px;right:20px;background:#ef4444;color:white;padding:12px 20px;border-radius:8px;z-index:1000;font-weight:600;';
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
      }
      
      throw error;
    } finally {
      set({ isSaving: false });
    }
  },
  
  load: async () => {
    set({ isLoading: true });
    
    try {
      // Laravel Sales API에서 최근 데이터 로드
      const response = await fetch('http://localhost:8000/api/sales?limit=50&sort=sale_date&order=desc');
      
      if (response.ok) {
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
          // Sales 데이터를 YKP Settlement 형식으로 변환
          const convertedRows = result.data.map((sale: any) => ({
            id: `settlement_${sale.id}`,
            seller: sale.salesperson || '미기재',
            dealerId: sale.dealer_code || 'DEFAULT',
            dealerName: '대리점명', // API에서 가져올 예정
            carrier: sale.carrier || 'SK',
            activationType: sale.activation_type || '신규',
            modelName: sale.model_name || '',
            activationDate: sale.sale_date || new Date().toISOString().split('T')[0],
            customerName: '고객명', // 개인정보는 마스킹
            customerPhone: sale.phone_number || '',
            faceValue: String(sale.base_price || 0),
            verbal1: String(sale.verbal1 || 0),
            verbal2: String(sale.verbal2 || 0),
            gradeAmount: String(sale.grade_amount || 0),
            additionalAmount: String(sale.additional_amount || 0),
            cashReceived: String(sale.cash_received || 0),
            payback: String(sale.payback || 0),
            memo: sale.memo || '',
            
            // 계산 필드들
            settlementAmount: String(sale.settlement_amount || 0),
            tax: String(sale.tax || 0),
            finalMargin: String(sale.margin_after_tax || 0)
          }));
          
          get().setRows(convertedRows);
          console.log('✅ API에서 데이터 로드 완료:', convertedRows.length + '건');
        } else {
          // API 데이터가 없으면 localStorage 시도
          const saved = localStorage.getItem('settlement_data');
          if (saved) {
            const rows = JSON.parse(saved);
            get().setRows(rows);
            console.log('📦 localStorage에서 데이터 로드:', rows.length + '건');
          } else {
            // 완전히 새로운 시작
            get().setRows([createNewRow(DEFAULT_PROFILE)]);
            console.log('🆕 새로운 데이터로 시작');
          }
        }
      } else {
        throw new Error(`API 호출 실패: ${response.status}`);
      }
      
    } catch (error) {
      console.error('데이터 로드 오류:', error);
      
      // API 실패 시 localStorage 폴백
      const saved = localStorage.getItem('settlement_data');
      if (saved) {
        const rows = JSON.parse(saved);
        get().setRows(rows);
        console.log('📦 오류로 인한 localStorage 폴백:', rows.length + '건');
      } else {
        get().setRows([createNewRow(DEFAULT_PROFILE)]);
      }
    } finally {
      set({ isLoading: false });
    }
  },
}));
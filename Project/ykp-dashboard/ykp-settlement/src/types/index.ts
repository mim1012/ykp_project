// 정산 데이터 타입
export interface SettlementRow {
  id: string;
  // 입력 필드
  seller: string;                // 판매자
  dealer: string;                // 대리점
  carrier: string;               // 통신사
  activationType: string;        // 개통방식 (신규/MNP/기변)
  modelName: string;             // 모델명
  activationDate: string;        // 개통일 (YYYY-MM-DD)
  customerName: string;          // 고객명
  priceSettling: number;         // 액면/셋팅가
  verbal1: number;               // 구두1
  verbal2: number;               // 구두2
  gradeAmount: number;           // 그레이드
  additionalAmount: number;      // 부가추가
  cashReceived: number;          // 현금받음 (+)
  payback: number;               // 페이백 (-)
  memo: string;                  // 메모
  
  // 정책 필드 (프로파일 기본값)
  simFee: number;                // 유심비 (+)
  mnpDiscount: number;           // 신규·번이 할인 (-)
  documentCash: number;          // 서류상현금개통
  taxRate: number;               // 세율
  
  // 계산 필드 (읽기 전용)
  totalRebate?: number;          // 리베총계
  settlementAmount?: number;     // 정산금
  tax?: number;                  // 부가세
  marginBeforeTax?: number;      // 세전마진
  marginAfterTax?: number;       // 세후마진
  
  // 검증 상태
  errors?: Record<string, string>;
}

// 지점/대리점 프로파일
export interface DealerProfile {
  branchId: string;
  dealerId: string;
  dealerName: string;
  simFee: number;                // 유심비 기본값
  mnpDiscount: number;           // MNP/신규 할인 기본값
  documentCash: number;          // 서류상현금 기본값
  taxRate: number;               // 세율 (0.133 = 13.3%)
  defaultCarrier: string;        // 기본 통신사
  defaultType: string;           // 기본 개통방식
  rebateFormulaId?: string;      // 리베이트 계산 공식 ID
}

// 컬럼 그룹 정의
export interface ColumnGroup {
  headerName: string;
  children: any[];
}

// 통신사 표준화 맵
export const CARRIER_MAP: Record<string, string> = {
  'sk': 'SKT',
  'skt': 'SKT',
  'SK': 'SKT',
  'kt': 'KT',
  'KT': 'KT',
  'lg': 'LGU+',
  'lgu': 'LGU+',
  'lgu+': 'LGU+',
  'LG': 'LGU+',
  'LGU': 'LGU+',
  'LGU+': 'LGU+',
  '알뜰': 'MVNO',
  'mvno': 'MVNO',
  'MVNO': 'MVNO'
};

// 개통방식 표준화
export const ACTIVATION_TYPES = ['신규', 'MNP', '기변'] as const;
export type ActivationType = typeof ACTIVATION_TYPES[number];

// 검증 규칙
export interface ValidationRule {
  field: string;
  required?: boolean;
  pattern?: RegExp;
  min?: number;
  max?: number;
  custom?: (value: any, row: SettlementRow) => string | null;
}
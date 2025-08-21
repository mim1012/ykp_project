// 숫자 포맷팅 유틸리티

/**
 * 숫자를 한국 원화 형식으로 포맷
 */
export const formatKRW = (value: number | null | undefined): string => {
  if (value === null || value === undefined || isNaN(value)) return '';
  return `₩${value.toLocaleString('ko-KR')}`;
};

/**
 * 한국 원화 문자열을 숫자로 파싱
 */
export const parseKRW = (value: string): number => {
  if (!value) return 0;
  // ₩, 콤마, 공백 제거
  const cleaned = value.replace(/[₩,\s]/g, '');
  const parsed = parseFloat(cleaned);
  return isNaN(parsed) ? 0 : parsed;
};

/**
 * 퍼센트 포맷 (소수점 1자리)
 */
export const formatPercent = (value: number): string => {
  if (value === null || value === undefined || isNaN(value)) return '';
  return `${(value * 100).toFixed(1)}%`;
};

/**
 * 퍼센트 문자열을 소수로 파싱
 */
export const parsePercent = (value: string): number => {
  if (!value) return 0;
  const cleaned = value.replace(/[%\s]/g, '');
  const parsed = parseFloat(cleaned);
  return isNaN(parsed) ? 0 : parsed / 100;
};

/**
 * 날짜 포맷 (YYYY-MM-DD)
 */
export const formatDate = (date: Date | string | null): string => {
  if (!date) return '';
  
  const d = typeof date === 'string' ? new Date(date) : date;
  if (isNaN(d.getTime())) return '';
  
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  
  return `${year}-${month}-${day}`;
};

/**
 * 날짜 문자열 파싱 및 검증
 */
export const parseDate = (value: string): Date | null => {
  if (!value) return null;
  
  // YYYY-MM-DD 형식 체크
  const regex = /^\d{4}-\d{2}-\d{2}$/;
  if (!regex.test(value)) return null;
  
  const date = new Date(value);
  if (isNaN(date.getTime())) return null;
  
  // 날짜가 유효한지 체크 (예: 2024-13-01 같은 경우 방지)
  const [year, month, day] = value.split('-').map(Number);
  if (date.getFullYear() !== year || 
      date.getMonth() + 1 !== month || 
      date.getDate() !== day) {
    return null;
  }
  
  return date;
};

/**
 * 숫자 포맷 (천 단위 콤마)
 */
export const formatNumber = (value: number | null | undefined): string => {
  if (value === null || value === undefined || isNaN(value)) return '';
  return value.toLocaleString('ko-KR');
};

/**
 * 숫자 문자열 파싱
 */
export const parseNumber = (value: string): number => {
  if (!value) return 0;
  const cleaned = value.replace(/[,\s]/g, '');
  const parsed = parseFloat(cleaned);
  return isNaN(parsed) ? 0 : parsed;
};

/**
 * 부호 적용 (절대값 입력 받아서 부호 적용)
 */
export const applySign = (value: number, negative: boolean = false): number => {
  const absValue = Math.abs(value);
  return negative ? -absValue : absValue;
};

/**
 * 통신사 표준화
 */
export const normalizeCarrier = (value: string): string => {
  const normalized = value.trim().toUpperCase();
  const carrierMap: Record<string, string> = {
    'SK': 'SKT',
    'SKT': 'SKT',
    'KT': 'KT',
    'LG': 'LGU+',
    'LGU': 'LGU+',
    'LGU+': 'LGU+',
    'MVNO': 'MVNO',
    '알뜰': 'MVNO'
  };
  
  return carrierMap[normalized] || value;
};

/**
 * 개통방식 표준화
 */
export const normalizeActivationType = (value: string): string => {
  const normalized = value.trim();
  const typeMap: Record<string, string> = {
    '신규': '신규',
    'MNP': 'MNP',
    '번호이동': 'MNP',
    '번이': 'MNP',
    '기변': '기변',
    '기기변경': '기변'
  };
  
  return typeMap[normalized] || value;
};
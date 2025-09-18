import { SettlementRow, CARRIER_MAP, ACTIVATION_TYPES } from '../types';
import { parseDate } from './formatters';

/**
 * 행 검증
 */
export const validateRow = (row: SettlementRow): Record<string, string> => {
  const errors: Record<string, string> = {};
  
  // 필수 필드 검증
  if (!row.seller?.trim()) {
    errors.seller = '판매자는 필수입니다';
  }
  
  if (!row.dealer?.trim()) {
    errors.dealer = '대리점은 필수입니다';
  }
  
  if (!row.carrier?.trim()) {
    errors.carrier = '통신사는 필수입니다';
  } else if (!Object.values(CARRIER_MAP).includes(row.carrier)) {
    errors.carrier = '올바른 통신사를 선택하세요 (SKT/KT/LGU+/MVNO)';
  }
  
  if (!row.activationType?.trim()) {
    errors.activationType = '개통방식은 필수입니다';
  } else if (!ACTIVATION_TYPES.includes(row.activationType as any)) {
    errors.activationType = '올바른 개통방식을 선택하세요 (신규/MNP/기변)';
  }
  
  if (!row.modelName?.trim()) {
    errors.modelName = '모델명은 필수입니다';
  }
  
  if (!row.activationDate) {
    errors.activationDate = '개통일은 필수입니다';
  } else if (!parseDate(row.activationDate)) {
    errors.activationDate = '날짜 형식이 올바르지 않습니다 (YYYY-MM-DD)';
  }
  
  if (!row.customerName?.trim()) {
    errors.customerName = '고객명은 필수입니다';
  }
  
  // 숫자 필드 검증
  if (row.priceSettling < 0) {
    errors.priceSettling = '액면가는 0 이상이어야 합니다';
  }
  
  if (row.taxRate && (row.taxRate < 0 || row.taxRate > 1)) {
    errors.taxRate = '세율은 0~1 사이여야 합니다';
  }
  
  return errors;
};

/**
 * 전체 데이터 검증
 */
export const validateAllRows = (rows: SettlementRow[]): boolean => {
  return rows.every(row => {
    const errors = validateRow(row);
    return Object.keys(errors).length === 0;
  });
};

/**
 * 엑셀 붙여넣기 데이터 파싱 및 검증
 */
export const parseClipboardData = (
  clipboardData: string,
  columns: string[]
): { data: any[], errors: string[] } => {
  const errors: string[] = [];
  const rows = clipboardData.split('\n').filter(row => row.trim());
  
  const data = rows.map((row, rowIndex) => {
    const cells = row.split('\t');
    const rowData: any = {};
    
    cells.forEach((cell, colIndex) => {
      if (colIndex < columns.length) {
        const column = columns[colIndex];
        rowData[column] = cell.trim();
      }
    });
    
    return rowData;
  });
  
  return { data, errors };
};

/**
 * 통신사 자동 교정
 */
export const autoCorrectCarrier = (value: string): string => {
  const upperValue = value.trim().toUpperCase();
  
  // 부분 매칭
  if (upperValue.includes('SK')) return 'SKT';
  if (upperValue.includes('KT')) return 'KT';
  if (upperValue.includes('LG') || upperValue.includes('LGU')) return 'LGU+';
  if (upperValue.includes('알뜰') || upperValue.includes('MVNO')) return 'MVNO';
  
  return value;
};

/**
 * 개통방식 자동 교정
 */
export const autoCorrectActivationType = (value: string): string => {
  const trimmed = value.trim();
  
  if (trimmed.includes('신규')) return '신규';
  if (trimmed.includes('MNP') || trimmed.includes('번호이동') || trimmed.includes('번이')) return 'MNP';
  if (trimmed.includes('기변') || trimmed.includes('기기변경')) return '기변';
  
  return value;
};
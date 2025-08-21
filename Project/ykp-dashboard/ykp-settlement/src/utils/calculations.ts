import { SettlementRow } from '../types';

/**
 * 정산 계산 로직
 */
export const calculateSettlement = (row: SettlementRow): SettlementRow => {
  // 리베총계 = 액면가 + 구두1 + 구두2 + 그레이드 + 부가추가
  const totalRebate = 
    (row.priceSettling || 0) +
    (row.verbal1 || 0) +
    (row.verbal2 || 0) +
    (row.gradeAmount || 0) +
    (row.additionalAmount || 0);
  
  // 정산금 = 리베총계 - 서류상현금 + 유심비 + 신규/MNP할인 
  const settlementAmount = 
    totalRebate -
    (row.documentCash || 0) +
    Math.abs(row.simFee || 0) +      // 유심비는 항상 +
    -Math.abs(row.mnpDiscount || 0);  // MNP할인은 항상 -
  
  // 세금 = 정산금 * 세율
  const tax = Math.round(settlementAmount * (row.taxRate || 0.133));
  
  // 세전마진 = 정산금 - 세금 + 현금받음 + 페이백
  const marginBeforeTax = 
    settlementAmount - 
    tax + 
    Math.abs(row.cashReceived || 0) +  // 현금받음은 항상 +
    -Math.abs(row.payback || 0);       // 페이백은 항상 -
  
  // 세후마진 = 세전마진 (세금 이미 차감됨)
  const marginAfterTax = marginBeforeTax;
  
  return {
    ...row,
    totalRebate,
    settlementAmount,
    tax,
    marginBeforeTax,
    marginAfterTax
  };
};

/**
 * 합계 계산
 */
export const calculateTotals = (rows: SettlementRow[]): {
  count: number;
  totalRebate: number;
  settlementAmount: number;
  tax: number;
  marginBeforeTax: number;
  marginAfterTax: number;
  avgMargin: number;
} => {
  const totals = rows.reduce((acc, row) => {
    const calculated = calculateSettlement(row);
    return {
      count: acc.count + 1,
      totalRebate: acc.totalRebate + (calculated.totalRebate || 0),
      settlementAmount: acc.settlementAmount + (calculated.settlementAmount || 0),
      tax: acc.tax + (calculated.tax || 0),
      marginBeforeTax: acc.marginBeforeTax + (calculated.marginBeforeTax || 0),
      marginAfterTax: acc.marginAfterTax + (calculated.marginAfterTax || 0)
    };
  }, {
    count: 0,
    totalRebate: 0,
    settlementAmount: 0,
    tax: 0,
    marginBeforeTax: 0,
    marginAfterTax: 0
  });
  
  return {
    ...totals,
    avgMargin: totals.count > 0 ? totals.marginAfterTax / totals.count : 0
  };
};
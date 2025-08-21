// 숫자 포맷 함수
export const formatCurrency = (num) => {
    return '₩' + Number(num).toLocaleString('ko-KR');
};

export const formatNumber = (num) => {
    return Number(num).toLocaleString('ko-KR');
};

export const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ko-KR');
};

export const formatDateTime = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('ko-KR');
};
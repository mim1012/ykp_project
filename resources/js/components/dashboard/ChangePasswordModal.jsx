import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Button } from '../ui/Button';
import { LoadingSpinner } from '../ui/LoadingSpinner';
import api from '../../services/api';

export const ChangePasswordModal = ({ isOpen, onClose }) => {
    const [formData, setFormData] = useState({
        current_password: '',
        password: '',
        password_confirmation: ''
    });
    const [errors, setErrors] = useState({});
    const [isLoading, setIsLoading] = useState(false);
    const [successMessage, setSuccessMessage] = useState('');
    const [passwordStrength, setPasswordStrength] = useState({
        hasMinLength: false,
        hasLetter: false,
        hasNumber: false,
        hasSymbol: false
    });

    // 비밀번호 강도 검증
    const checkPasswordStrength = (password) => {
        setPasswordStrength({
            hasMinLength: password.length >= 8,
            hasLetter: /[a-zA-Z]/.test(password),
            hasNumber: /\d/.test(password),
            hasSymbol: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        });
    };

    // 입력값 변경 핸들러
    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));

        // 새 비밀번호 입력 시 강도 체크
        if (name === 'password') {
            checkPasswordStrength(value);
        }

        // 에러 메시지 초기화
        if (errors[name]) {
            setErrors(prev => ({ ...prev, [name]: null }));
        }
    };

    // 폼 제출 핸들러
    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});
        setSuccessMessage('');

        // 클라이언트 측 검증
        const newErrors = {};
        if (!formData.current_password) {
            newErrors.current_password = '현재 비밀번호를 입력해주세요.';
        }
        if (!formData.password) {
            newErrors.password = '새 비밀번호를 입력해주세요.';
        }
        if (!formData.password_confirmation) {
            newErrors.password_confirmation = '비밀번호 확인을 입력해주세요.';
        }
        if (formData.password && formData.password_confirmation && formData.password !== formData.password_confirmation) {
            newErrors.password_confirmation = '비밀번호가 일치하지 않습니다.';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setIsLoading(true);

        try {
            const response = await api.post('/users/change-password', formData);

            if (response.success) {
                setSuccessMessage(response.message || '비밀번호가 성공적으로 변경되었습니다.');

                // 2초 후 모달 닫기
                setTimeout(() => {
                    onClose();
                    // 폼 초기화
                    setFormData({
                        current_password: '',
                        password: '',
                        password_confirmation: ''
                    });
                    setPasswordStrength({
                        hasMinLength: false,
                        hasLetter: false,
                        hasNumber: false,
                        hasSymbol: false
                    });
                }, 2000);
            }
        } catch (error) {
            // 서버 에러 처리
            if (error.status === 422 && error.data?.errors) {
                setErrors(error.data.errors);
            } else {
                setErrors({ general: error.message || '비밀번호 변경 중 오류가 발생했습니다.' });
            }
        } finally {
            setIsLoading(false);
        }
    };

    // 모달 닫기 핸들러
    const handleClose = () => {
        if (!isLoading) {
            setFormData({
                current_password: '',
                password: '',
                password_confirmation: ''
            });
            setErrors({});
            setSuccessMessage('');
            setPasswordStrength({
                hasMinLength: false,
                hasLetter: false,
                hasNumber: false,
                hasSymbol: false
            });
            onClose();
        }
    };

    return (
        <AnimatePresence>
            {isOpen && (
                <>
                    {/* 오버레이 */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={handleClose}
                        className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
                    >
                        {/* 모달 컨텐츠 */}
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95, y: 20 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.95, y: 20 }}
                            transition={{ duration: 0.2 }}
                            onClick={(e) => e.stopPropagation()}
                            className="bg-white rounded-lg shadow-xl max-w-md w-full p-6"
                        >
                            {/* 헤더 */}
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-xl font-bold text-gray-900">비밀번호 변경</h2>
                                <button
                                    onClick={handleClose}
                                    disabled={isLoading}
                                    className="text-gray-400 hover:text-gray-600 transition-colors disabled:opacity-50"
                                >
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {/* 성공 메시지 */}
                            {successMessage && (
                                <motion.div
                                    initial={{ opacity: 0, y: -10 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg"
                                >
                                    <div className="flex items-center gap-2 text-green-800">
                                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                        </svg>
                                        <span className="font-medium">{successMessage}</span>
                                    </div>
                                </motion.div>
                            )}

                            {/* 일반 에러 메시지 */}
                            {errors.general && (
                                <motion.div
                                    initial={{ opacity: 0, y: -10 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg"
                                >
                                    <div className="flex items-center gap-2 text-red-800">
                                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                        </svg>
                                        <span className="font-medium">{errors.general}</span>
                                    </div>
                                </motion.div>
                            )}

                            {/* 폼 */}
                            <form onSubmit={handleSubmit} className="space-y-4">
                                {/* 현재 비밀번호 */}
                                <div>
                                    <label htmlFor="current_password" className="block text-sm font-medium text-gray-700 mb-1">
                                        현재 비밀번호
                                    </label>
                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        value={formData.current_password}
                                        onChange={handleInputChange}
                                        disabled={isLoading}
                                        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed ${
                                            errors.current_password ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    />
                                    {errors.current_password && (
                                        <p className="mt-1 text-sm text-red-600">{errors.current_password}</p>
                                    )}
                                </div>

                                {/* 새 비밀번호 */}
                                <div>
                                    <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                                        새 비밀번호
                                    </label>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        value={formData.password}
                                        onChange={handleInputChange}
                                        disabled={isLoading}
                                        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed ${
                                            errors.password ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    />
                                    {errors.password && (
                                        <p className="mt-1 text-sm text-red-600">{errors.password}</p>
                                    )}

                                    {/* 비밀번호 강도 표시 */}
                                    {formData.password && (
                                        <div className="mt-2 space-y-1">
                                            <p className="text-xs font-medium text-gray-600">비밀번호 요구사항:</p>
                                            <div className="grid grid-cols-2 gap-2">
                                                <div className={`flex items-center gap-1 text-xs ${passwordStrength.hasMinLength ? 'text-green-600' : 'text-gray-400'}`}>
                                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                    <span>최소 8자</span>
                                                </div>
                                                <div className={`flex items-center gap-1 text-xs ${passwordStrength.hasLetter ? 'text-green-600' : 'text-gray-400'}`}>
                                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                    <span>영문 포함</span>
                                                </div>
                                                <div className={`flex items-center gap-1 text-xs ${passwordStrength.hasNumber ? 'text-green-600' : 'text-gray-400'}`}>
                                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                    <span>숫자 포함</span>
                                                </div>
                                                <div className={`flex items-center gap-1 text-xs ${passwordStrength.hasSymbol ? 'text-green-600' : 'text-gray-400'}`}>
                                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                    <span>특수문자 포함</span>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {/* 비밀번호 확인 */}
                                <div>
                                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 mb-1">
                                        비밀번호 확인
                                    </label>
                                    <input
                                        type="password"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        value={formData.password_confirmation}
                                        onChange={handleInputChange}
                                        disabled={isLoading}
                                        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed ${
                                            errors.password_confirmation ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    />
                                    {errors.password_confirmation && (
                                        <p className="mt-1 text-sm text-red-600">{errors.password_confirmation}</p>
                                    )}
                                </div>

                                {/* 버튼들 */}
                                <div className="flex gap-3 mt-6">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={handleClose}
                                        disabled={isLoading}
                                        className="flex-1"
                                    >
                                        취소
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="primary"
                                        disabled={isLoading}
                                        className="flex-1"
                                    >
                                        {isLoading ? (
                                            <div className="flex items-center gap-2">
                                                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                                                <span>변경 중...</span>
                                            </div>
                                        ) : (
                                            '비밀번호 변경'
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </motion.div>
                    </motion.div>
                </>
            )}
        </AnimatePresence>
    );
};

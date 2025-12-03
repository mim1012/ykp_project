// Q&A 권한 테스트 - 로컬 환경
import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

// 테스트 계정
const ACCOUNTS = {
    headquarters: { email: 'admin@ykp.com', password: 'password', role: 'headquarters' },
    branch: { email: 'branch@ykp.com', password: 'password', role: 'branch' },
    store: { email: 'store@ykp.com', password: 'password', role: 'store' },
    otherStore: { email: 'br001-001@ykp.com', password: 'password', role: 'store' } // 다른 매장
};

// 로그인 헬퍼 함수
async function login(page, account) {
    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name="email"]', account.email);
    await page.fill('input[name="password"]', account.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard/, { timeout: 15000 });
    console.log(`✅ ${account.email} 로그인 성공`);
}

// API 로그인 헬퍼
async function apiLogin(request, account) {
    const response = await request.post(`${BASE_URL}/login`, {
        form: {
            email: account.email,
            password: account.password,
            _token: '' // CSRF는 세션에서 처리
        }
    });
    return response;
}

test.describe('Q&A 권한 테스트', () => {

    test('1. 매장에서 Q&A 작성 가능', async ({ page }) => {
        console.log('\n🚀 매장 Q&A 작성 테스트...');

        await login(page, ACCOUNTS.store);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        // 질문하기 버튼 확인
        const writeBtn = page.locator('button:has-text("질문하기")');
        await expect(writeBtn).toBeVisible();
        console.log('✅ 매장 계정에서 "질문하기" 버튼 확인');

        // 글쓰기 모달 열기
        await writeBtn.click();
        await page.waitForSelector('#write-modal:not(.hidden)', { timeout: 5000 });

        // 글 작성
        const timestamp = Date.now();
        const testTitle = `테스트 질문 ${timestamp}`;
        await page.fill('input[name="title"]', testTitle);
        await page.fill('textarea[name="content"]', '이것은 자동화 테스트로 작성된 질문입니다.');

        // 등록
        await page.click('#write-form button[type="submit"]');
        await page.waitForTimeout(2000);

        // 등록 확인
        const postExists = await page.locator(`text=${testTitle}`).isVisible();
        expect(postExists).toBeTruthy();
        console.log('✅ 매장 Q&A 글 작성 성공');
    });

    test('2. 지사에서 Q&A 작성 가능 (새로 추가된 권한)', async ({ page }) => {
        console.log('\n🚀 지사 Q&A 작성 테스트...');

        await login(page, ACCOUNTS.branch);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        // 질문하기 버튼 확인 (지사도 가능해야 함)
        const writeBtn = page.locator('button:has-text("질문하기")');
        await expect(writeBtn).toBeVisible();
        console.log('✅ 지사 계정에서 "질문하기" 버튼 확인');

        // 글쓰기 모달 열기
        await writeBtn.click();
        await page.waitForSelector('#write-modal:not(.hidden)', { timeout: 5000 });

        // 글 작성
        const timestamp = Date.now();
        const testTitle = `지사 테스트 질문 ${timestamp}`;
        await page.fill('input[name="title"]', testTitle);
        await page.fill('textarea[name="content"]', '지사에서 작성한 테스트 질문입니다.');

        // 등록
        await page.click('#write-form button[type="submit"]');
        await page.waitForTimeout(2000);

        // 등록 확인
        const postExists = await page.locator(`text=${testTitle}`).isVisible();
        expect(postExists).toBeTruthy();
        console.log('✅ 지사 Q&A 글 작성 성공');
    });

    test('3. 본사는 Q&A 작성 불가 (답변만 가능)', async ({ page }) => {
        console.log('\n🚀 본사 Q&A 작성 불가 테스트...');

        await login(page, ACCOUNTS.headquarters);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        // 질문하기 버튼이 없어야 함
        const writeBtn = page.locator('button:has-text("질문하기")');
        const isVisible = await writeBtn.isVisible().catch(() => false);
        expect(isVisible).toBeFalsy();
        console.log('✅ 본사 계정에서는 "질문하기" 버튼이 없음 (정상)');
    });

    test('4. 본사에서 Q&A 답변 가능', async ({ page }) => {
        console.log('\n🚀 본사 Q&A 답변 테스트...');

        // 먼저 매장으로 글 작성
        await login(page, ACCOUNTS.store);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        const writeBtn = page.locator('button:has-text("질문하기")');
        await writeBtn.click();
        await page.waitForSelector('#write-modal:not(.hidden)', { timeout: 5000 });

        const timestamp = Date.now();
        const testTitle = `답변 테스트 질문 ${timestamp}`;
        await page.fill('input[name="title"]', testTitle);
        await page.fill('textarea[name="content"]', '본사 답변 테스트용 질문입니다.');
        await page.click('#write-form button[type="submit"]');
        await page.waitForTimeout(2000);
        console.log('✅ 테스트용 질문 작성 완료');

        // 본사로 로그인
        await page.goto(`${BASE_URL}/logout`);
        await login(page, ACCOUNTS.headquarters);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        // 방금 작성한 글 클릭
        await page.click(`text=${testTitle}`);
        await page.waitForSelector('#detail-modal:not(.hidden)', { timeout: 5000 });

        // 답변 작성 폼 확인
        const replyTextarea = page.locator('#reply-content');
        await expect(replyTextarea).toBeVisible();
        console.log('✅ 본사 계정에서 답변 폼 확인');

        // 답변 작성
        await replyTextarea.fill('본사에서 작성한 공식 답변입니다.');
        await page.click('button:has-text("답변 등록")');
        await page.waitForTimeout(2000);

        // 답변 등록 확인
        const replyExists = await page.locator('text=본사에서 작성한 공식 답변입니다.').isVisible();
        expect(replyExists).toBeTruthy();
        console.log('✅ 본사 답변 등록 성공');
    });

    test('5. 비밀글 - 작성자와 본사/지사만 열람 가능', async ({ page, context }) => {
        console.log('\n🚀 비밀글 권한 테스트...');

        // 매장에서 비밀글 작성
        await login(page, ACCOUNTS.store);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        const writeBtn = page.locator('button:has-text("질문하기")');
        await writeBtn.click();
        await page.waitForSelector('#write-modal:not(.hidden)', { timeout: 5000 });

        const timestamp = Date.now();
        const privateTitle = `비밀글 테스트 ${timestamp}`;
        await page.fill('input[name="title"]', privateTitle);
        await page.fill('textarea[name="content"]', '이것은 비밀글입니다. 본사와 작성자만 볼 수 있어야 합니다.');

        // 비밀글 체크
        await page.check('input[name="is_private"]');
        console.log('✅ 비밀글 체크됨');

        await page.click('#write-form button[type="submit"]');
        await page.waitForTimeout(2000);
        console.log('✅ 비밀글 작성 완료');

        // 작성자 본인은 볼 수 있어야 함
        await page.click(`text=${privateTitle}`);
        await page.waitForSelector('#detail-modal:not(.hidden)', { timeout: 5000 });
        const contentVisible = await page.locator('text=이것은 비밀글입니다').isVisible();
        expect(contentVisible).toBeTruthy();
        console.log('✅ 작성자 본인은 비밀글 열람 가능');

        await page.click('#detail-modal button:has-text("×")');
        await page.waitForTimeout(500);

        // 본사로 로그인 - 볼 수 있어야 함
        await page.goto(`${BASE_URL}/logout`);
        await login(page, ACCOUNTS.headquarters);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        const hqCanSee = await page.locator(`text=${privateTitle}`).isVisible();
        expect(hqCanSee).toBeTruthy();
        console.log('✅ 본사는 비밀글 목록에서 확인 가능');

        await page.click(`text=${privateTitle}`);
        await page.waitForSelector('#detail-modal:not(.hidden)', { timeout: 5000 });
        const hqContentVisible = await page.locator('text=이것은 비밀글입니다').isVisible();
        expect(hqContentVisible).toBeTruthy();
        console.log('✅ 본사는 비밀글 내용 열람 가능');
    });

    test('6. 다른 매장은 비밀글 열람 불가', async ({ page, request }) => {
        console.log('\n🚀 다른 매장 비밀글 접근 제한 테스트...');

        // 먼저 store 계정으로 비밀글 작성
        await login(page, ACCOUNTS.store);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        const writeBtn = page.locator('button:has-text("질문하기")');
        await writeBtn.click();
        await page.waitForSelector('#write-modal:not(.hidden)', { timeout: 5000 });

        const timestamp = Date.now();
        const privateTitle = `다른매장접근테스트 ${timestamp}`;
        await page.fill('input[name="title"]', privateTitle);
        await page.fill('textarea[name="content"]', '다른 매장에서는 이 글을 볼 수 없어야 합니다.');
        await page.check('input[name="is_private"]');
        await page.click('#write-form button[type="submit"]');
        await page.waitForTimeout(2000);

        // 작성된 글 ID 확인
        const postLink = page.locator(`text=${privateTitle}`);
        await expect(postLink).toBeVisible();
        console.log('✅ 비밀글 작성 완료');

        // 다른 매장 계정으로 로그인
        await page.goto(`${BASE_URL}/logout`);
        await login(page, ACCOUNTS.otherStore);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        // 다른 매장에서는 해당 비밀글이 보이지 않아야 함
        const otherCanSee = await page.locator(`text=${privateTitle}`).isVisible().catch(() => false);
        expect(otherCanSee).toBeFalsy();
        console.log('✅ 다른 매장에서는 비밀글이 목록에 표시되지 않음 (정상)');
    });

    test('7. 매장은 자기 글에만 댓글 가능', async ({ page }) => {
        console.log('\n🚀 매장 자기 글 댓글 권한 테스트...');

        // 매장에서 글 작성
        await login(page, ACCOUNTS.store);
        await page.goto(`${BASE_URL}/community/qna`);
        await page.waitForLoadState('networkidle');

        const writeBtn = page.locator('button:has-text("질문하기")');
        await writeBtn.click();
        await page.waitForSelector('#write-modal:not(.hidden)', { timeout: 5000 });

        const timestamp = Date.now();
        const testTitle = `댓글테스트 ${timestamp}`;
        await page.fill('input[name="title"]', testTitle);
        await page.fill('textarea[name="content"]', '자기 글에 댓글 달기 테스트입니다.');
        await page.click('#write-form button[type="submit"]');
        await page.waitForTimeout(2000);

        // 자기 글 클릭
        await page.click(`text=${testTitle}`);
        await page.waitForSelector('#detail-modal:not(.hidden)', { timeout: 5000 });

        // 답변 작성 폼 확인 (자기 글이므로 있어야 함)
        const replyForm = page.locator('#reply-content');
        await expect(replyForm).toBeVisible();
        console.log('✅ 매장은 자기 글에 답변 폼이 있음');

        // 답변 작성
        await replyForm.fill('매장에서 추가 질문입니다.');
        await page.click('button:has-text("답변 등록")');
        await page.waitForTimeout(2000);

        const replyAdded = await page.locator('text=매장에서 추가 질문입니다.').isVisible();
        expect(replyAdded).toBeTruthy();
        console.log('✅ 매장이 자기 글에 댓글 작성 성공');
    });
});

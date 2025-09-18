# 코드 커버리지 설정 가이드

## Windows에서 PCOV 설치

### 방법 1: PECL을 통한 설치 (권장)
```bash
# PECL이 설치되어 있다면
pecl install pcov

# php.ini에 추가
extension=pcov
pcov.enabled=1
```

### 방법 2: 수동 설치
1. [PECL PCOV 페이지](https://pecl.php.net/package/pcov)에서 Windows DLL 다운로드
2. PHP 8.4 NTS x64 버전 선택
3. `php_pcov.dll`을 PHP `ext` 폴더에 복사
4. `php.ini`에 추가:
   ```ini
   extension=pcov
   pcov.enabled=1
   pcov.directory=.
   ```

### 방법 3: Xdebug 대안 (개발 환경)
1. [Xdebug 다운로드](https://xdebug.org/download)
2. PHP 8.4 TS/NTS x64 버전 선택
3. `php_xdebug.dll`을 PHP `ext` 폴더에 복사
4. `php.ini`에 추가:
   ```ini
   zend_extension=xdebug
   xdebug.mode=coverage
   ```

## 커버리지 측정 없이 테스트 실행

현재 커버리지 드라이버가 없어도 테스트는 실행 가능합니다:

```bash
# 커버리지 없이 테스트만 실행
./vendor/bin/phpunit --no-coverage

# 특정 테스트 스위트 실행
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Feature
```

## 커버리지 측정 명령어

### PCOV 사용 시:
```bash
./vendor/bin/phpunit --configuration phpunit-coverage.xml
```

### Xdebug 사용 시:
```bash
XDEBUG_MODE=coverage ./vendor/bin/phpunit --configuration phpunit-coverage.xml
```

## 커버리지 목표

| 컴포넌트 | 현재 | 목표 | 우선순위 |
|---------|------|------|---------|
| Models | - | 80% | High |
| Controllers | - | 75% | High |
| Helpers/SalesCalculator | - | 95% | Critical |
| Services | - | 85% | High |
| Middleware | - | 70% | Medium |

## 커버리지 리포트 확인

```bash
# HTML 리포트 생성 후 브라우저에서 확인
start coverage-report/index.html

# 터미널에서 간단히 확인
./vendor/bin/phpunit --coverage-text
```
# Page snapshot

```yaml
- generic [ref=e2]:
  - generic [ref=e3]:
    - generic [ref=e4]: "Y"
    - heading "YKP ERP 로그인" [level=2] [ref=e5]
    - paragraph [ref=e6]: 계정에 로그인하여 대시보드에 접속하세요
  - generic [ref=e8]:
    - generic [ref=e9]:
      - generic [ref=e10]: 이메일 주소
      - textbox "이메일 주소" [ref=e11]
    - generic [ref=e12]:
      - generic [ref=e13]: 비밀번호
      - textbox "비밀번호" [ref=e14]
    - generic [ref=e16]:
      - checkbox "로그인 상태 유지" [ref=e17]
      - generic [ref=e18]: 로그인 상태 유지
    - button "로그인" [ref=e20] [cursor=pointer]
    - link "계정이 없으신가요? 회원가입" [ref=e22] [cursor=pointer]:
      - /url: http://127.0.0.1:8000/register
  - generic [ref=e23]:
    - heading "테스트 계정 (개발용)" [level=3] [ref=e24]
    - generic [ref=e25]:
      - generic [ref=e26]: "본사: hq@ykp.com / 123456"
      - generic [ref=e27]: "지사: branch@ykp.com / 123456"
      - generic [ref=e28]: "매장: store@ykp.com / 123456"
```
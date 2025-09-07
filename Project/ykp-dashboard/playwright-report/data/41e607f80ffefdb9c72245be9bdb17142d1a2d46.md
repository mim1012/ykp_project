# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - generic [ref=e4]:
      - generic [ref=e5]:
        - heading "통합 관리" [level=1] [ref=e6]
        - generic [ref=e8]: 본사 관리자
      - link "대시보드" [ref=e10] [cursor=pointer]:
        - /url: /dashboard
  - main [ref=e11]:
    - generic [ref=e12]:
      - navigation [ref=e14]:
        - button "🏪 매장 관리" [ref=e15] [cursor=pointer]
        - button "🏢 지사 관리" [ref=e16] [cursor=pointer]
        - button "👥 사용자 관리" [ref=e17] [cursor=pointer]
      - generic [ref=e18]:
        - generic [ref=e19]:
          - heading "매장 목록" [level=2] [ref=e21]
          - button "➕ 매장 추가" [ref=e22] [cursor=pointer]
        - generic [ref=e25]:
          - generic [ref=e26]: ❌
          - generic [ref=e27]: 매장 데이터 로드 실패
          - generic [ref=e28]: "HTTP 404: Not Found"
          - button "🔄 다시 시도" [ref=e29] [cursor=pointer]
```
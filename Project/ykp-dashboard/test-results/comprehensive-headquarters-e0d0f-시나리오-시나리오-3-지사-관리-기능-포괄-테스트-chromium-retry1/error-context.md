# Page snapshot

```yaml
- generic [ref=e1]:
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
        - button "🏢 지사 관리" [active] [ref=e16] [cursor=pointer]
        - button "👥 사용자 관리" [ref=e17] [cursor=pointer]
      - generic [ref=e18]:
        - generic [ref=e19]:
          - heading "지사 목록" [level=2] [ref=e20]
          - button "➕ 지사 추가" [ref=e21] [cursor=pointer]
        - generic [ref=e23]: "❌ 지사 목록 로드 실패: Unexpected token '<', \""
```
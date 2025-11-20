# Git Workflow & Branching Strategy

This project follows **GitHub Flow** optimized for **1-person development with AI assistants** (Claude Code + Cursor).

## ⚠️ IMPORTANT: Branching Strategy Clarification

**Active Strategy**: AI-Optimized GitHub Flow (documented in this file)

**Note**: The repository also contains `BRANCHING_STRATEGY.md` which describes traditional Git Flow (main/staging/develop). **That document is legacy documentation**. For current development with Claude Code and Cursor AI, **follow the AI-specific branching strategy in this file** (`claude/*`, `cursor/*`, `feature/*` branches from `main`).

**Why AI-Specific GitHub Flow?**
- ✅ Clearer attribution (know which AI tool made which changes)
- ✅ Prevents merge conflicts between AI assistants
- ✅ Simpler workflow for solo developer + AI collaboration
- ✅ No need for long-lived `develop`/`staging` branches
- ✅ Faster iterations and deployments

If you need to reference traditional Git Flow for some reason, see `BRANCHING_STRATEGY.md`, but be aware it's not optimized for AI-assisted development.

## Branch Structure

```
main (production, always deployable)
  ├── claude/* (Claude Code 전용 브랜치)
  │   ├── claude/sales-feature
  │   ├── claude/dashboard-optimization
  │   └── claude/fix-calculation
  ├── cursor/* (Cursor AI 전용 브랜치)
  │   ├── cursor/ui-improvements
  │   ├── cursor/refactor-components
  │   └── cursor/add-feature
  └── feature/* (Manual work or experiments)
      ├── feature/manual-hotfix
      └── feature/experimental
```

## Why AI-Specific Branches?

**Problem**: Claude Code와 Cursor가 같은 브랜치에서 작업하면 충돌 가능성↑
**Solution**: 각 AI 도구가 전용 브랜치에서 작업하여 **작업 히스토리 명확화**

**Benefits**:
- ✅ AI 도구 간 충돌 방지
- ✅ 작업 주체 명확 (커밋 히스토리로 추적 가능)
- ✅ AI별 코드 스타일 일관성 유지
- ✅ 롤백 시 영향 범위 파악 용이

## Branch Naming Convention

**Format**: `<tool>/<domain>-<description>`

### Claude Code Branches (claude/*)
```bash
# Sales Management (판매 관리)
claude/sales-bulk-import-v2
claude/sales-calculation-refactor
claude/fix-sales-precision

# Dashboard & Statistics (대시보드/통계)
claude/dashboard-chart-selector
claude/dashboard-optimization
claude/perf-dashboard-cache

# Store & Branch Management (매장/지사 관리)
claude/store-bulk-upload
claude/store-account-mgmt
claude/fix-store-rbac

# Calculation & Settlement (계산/정산)
claude/calculation-optimization
claude/settlement-automation
claude/fix-dealer-profile

# Backend/API Work (백엔드/API)
claude/api-endpoint-refactor
claude/database-migration
claude/security-enhancement
```

### Cursor Branches (cursor/*)
```bash
# UI/UX Improvements (UI/UX 개선)
cursor/ui-responsive-design
cursor/ui-dark-mode
cursor/component-library

# Frontend Features (프론트엔드 기능)
cursor/chart-improvements
cursor/form-validation
cursor/table-virtualization

# Styling & Layout (스타일링/레이아웃)
cursor/tailwind-refactor
cursor/mobile-layout
cursor/accessibility

# Component Refactoring (컴포넌트 리팩토링)
cursor/refactor-hooks
cursor/optimize-renders
cursor/split-components
```

### Manual/Experimental Branches (feature/*)
```bash
# Emergency fixes (긴급 수정)
feature/hotfix-critical-bug
feature/emergency-deploy

# Experiments (실험적 기능)
feature/experimental-ai-feature
feature/poc-new-architecture
```

## Workflow: Claude Code Development

```bash
# 1. Create Claude branch from main
git checkout main
git pull origin main
git checkout -b claude/sales-bulk-import

# 2. Let Claude Code do the work with multiple commits
# Claude will automatically commit with proper messages
git add .
git commit -m "🤖 feat(sales): add CSV parser for bulk import"
git commit -m "🤖 feat(sales): add validation for imported data"
git commit -m "🤖 test(sales): add unit tests for CSV parser"

# 3. Push and create Pull Request
git push origin claude/sales-bulk-import
# Create PR on GitHub: claude/sales-bulk-import → main

# 4. Request AI review (optional but recommended)
# Ask Claude Code: "Please review the code in this PR"
# Or use: /sc:analyze for automated review

# 5. After review, merge to main
# No approval required (1-person dev), but CI must pass
# Squash merge recommended for clean history

# 6. Delete branch after merge
git branch -d claude/sales-bulk-import
git push origin --delete claude/sales-bulk-import
```

## Workflow: Cursor Development

```bash
# 1. Create Cursor branch from main
git checkout main
git pull origin main
git checkout -b cursor/ui-improvements

# 2. Use Cursor AI for development
# Cursor Composer or Chat features
# Commits with Cursor tag
git add .
git commit -m "🔮 feat(ui): improve responsive layout"
git commit -m "🔮 style(ui): refactor Tailwind classes"

# 3. Push and create Pull Request
git push origin cursor/ui-improvements
# Create PR: cursor/ui-improvements → main

# 4. Review in Claude Code (cross-check)
# Switch to Claude Code and ask:
# "Please review the code in cursor/ui-improvements branch"

# 5. Merge to main after CI passes
# Delete branch
git branch -d cursor/ui-improvements
git push origin --delete cursor/ui-improvements
```

## Workflow: Emergency Hotfix

```bash
# 1. Create hotfix branch from main (use feature/* for clarity)
git checkout main
git checkout -b feature/hotfix-critical-bug

# 2. Fix immediately (manual or with AI)
git commit -m "🚨 hotfix: fix critical sales calculation bug"
composer test  # Must pass!

# 3. Deploy ASAP - Direct merge to main
git checkout main
git merge --no-ff feature/hotfix-critical-bug
git tag -a v1.3.1 -m "Hotfix v1.3.1 - Critical bug fix"
git push origin main --tags

# 4. Delete hotfix branch
git branch -d feature/hotfix-critical-bug
```

## AI Code Review Process

### How to Request Claude Code Review

```bash
# After pushing your branch
git push origin claude/your-feature

# In Claude Code chat:
"Please review the code in claude/your-feature branch:
- Check for potential bugs
- Verify test coverage
- Suggest improvements
- Check security issues"

# Or use slash command:
/sc:analyze
```

### How to Request Cursor Review

```bash
# In Cursor IDE
1. Open the PR in Cursor
2. Use Cursor Chat: "Review this PR for code quality"
3. Or use Cursor Composer for inline suggestions
```

### Cross-Review (Recommended)

**Best Practice**: Ask the other AI to review
- Claude branch → Ask Cursor to review
- Cursor branch → Ask Claude to review
- Different perspectives = Better code quality

## Branch Protection Rules (1-Person Dev Optimized)

**main branch:**
- ✅ Require pull requests (for history tracking)
- ⬜ **NO approval required** (1-person dev)
- ✅ Require status checks to pass (CI/CD tests)
- ⬜ Require branches to be up to date (optional, for flexibility)
- ❌ Allow force pushes (disabled for safety)
- ✅ Auto-merge after CI passes (optional, for speed)

**No develop branch needed** - GitHub Flow uses main only

## Branch Lifecycle Rules

1. **AI branches (claude/*, cursor/*)**: Maximum lifetime 3 days
   - Short-lived branches for focused changes
   - Merge quickly to avoid drift from main
   - Delete immediately after merge

2. **Manual branches (feature/*)**: Maximum lifetime 1 week
   - Break into smaller tasks if longer
   - Merge or close stale branches

3. **Hotfix branches**: Maximum lifetime 4 hours
   - Emergency only
   - Immediate merge + deploy

## Commit Message Convention with AI Tags

Follow conventional commits with AI tool identification:

```bash
# Format: <emoji> <type>(<scope>): <subject>

# Claude Code commits (use 🤖 emoji)
🤖 feat(sales): add bulk import via CSV
🤖 fix(dashboard): correct chart date range calculation
🤖 perf(api): optimize sales query with proper indexing
🤖 refactor(auth): extract RBAC logic to service
🤖 test(calculation): add unit tests for dealer profiles

# Cursor commits (use 🔮 emoji)
🔮 feat(ui): add responsive layout
🔮 style(components): refactor Tailwind classes
🔮 fix(ui): correct mobile menu positioning
🔮 refactor(hooks): extract custom hooks

# Manual commits (use standard emojis)
✨ feat(auth): implement 2FA
🐛 fix(critical): patch security vulnerability
🚨 hotfix: emergency production fix
📝 docs(readme): update installation guide
🔧 chore(deps): upgrade Laravel to 12.0
```

**Commit Types:**
- `feat`: New feature
- `fix`: Bug fix
- `perf`: Performance improvement
- `refactor`: Code refactoring (no functional change)
- `test`: Adding/updating tests
- `docs`: Documentation only
- `style`: Code style/formatting (no logic change)
- `chore`: Maintenance tasks
- `security`: Security fixes

**AI Tool Emojis:**
- 🤖 = Claude Code
- 🔮 = Cursor AI
- ✨ = Manual (new feature)
- 🐛 = Manual (bug fix)
- 🚨 = Emergency/Hotfix

## Module Dependencies & Impact

When working on these modules, be aware of impact:

**High Impact (affects many modules):**
- `SalesCalculator` helper → Sales, Calculation, Dashboard, Settlement
- `RBACMiddleware` → All API endpoints
- `User/Auth` → All modules (authentication required)

**Medium Impact:**
- `Sales` model → Dashboard, Statistics, Settlement, Reports
- `Store/Branch` models → Sales, Dashboard, User management

**Low Impact (isolated modules):**
- `DailyExpense`, `FixedExpense`, `Refund`, `Payroll`
- Can be developed independently

## Domain-Specific Development Notes

### Sales Module
- **ALWAYS test** with `SalesCalculatorTest` when changing calculation logic
- **Check Excel formulas** match specifications in CALCULATIONS.md
- **Verify RBAC** for all role types (headquarters/branch/store)

### Dashboard Module
- **Performance critical**: Check query count and response time
- **Cache properly**: Use 5-minute TTL for statistics
- **Test with real data**: Use seeded database for accurate testing

### Store/Branch Module
- **Queue testing**: Verify bulk operations trigger jobs correctly
- **Excel validation**: Test with malformed CSV/Excel files
- **User creation**: Ensure account generation works correctly

### Auth/RBAC Module
- **Security first**: Test all permission boundaries
- **Audit changes**: All auth changes require security review
- **Test all roles**: Verify headquarters, branch, and store access

---

**Back to**: [Development Docs Index](README.md) | [CLAUDE.md](../../CLAUDE.md)

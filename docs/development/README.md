# Development Documentation

Welcome to the YKP ERP Dashboard development documentation. This directory contains detailed guides extracted from the main CLAUDE.md for better organization and performance.

## 📚 Documentation Structure

### Core Guides

#### [API_GUIDE.md](API_GUIDE.md)
**API Architecture & Core Components**
- Authentication & authorization (RBAC, session-based)
- API response format and error handling
- Key API endpoints reference
- Core helper classes (SalesCalculator, FieldMapper)
- Application services pattern
- Background jobs & queue system
- Excel import/export functionality
- Performance optimizations

**When to read**: Working on API endpoints, understanding business logic, or debugging calculations.

---

#### [CALCULATIONS.md](CALCULATIONS.md)
**Sales Calculation Formulas**
- Detailed calculation formulas with examples
- Field mapping (Excel columns ↔ field names)
- Critical notes on deduction (SUBTRACTED, not added!)
- Tax calculation status (currently disabled)
- SalesCalculator usage examples
- FieldMapper usage examples
- Testing calculations
- Common calculation issues

**When to read**: Implementing sales features, debugging calculation errors, or adding new fields.

---

#### [DEPLOYMENT.md](DEPLOYMENT.md)
**Deployment & Environment Configuration**
- Environment separation strategy (local vs production)
- Local development environment setup
- Railway production environment configuration
- Supabase connection details
- Feature flags usage
- CI/CD pipeline (GitHub Actions)
- Database migrations in production
- Automated Supabase backup system

**When to read**: Setting up development environment, deploying to production, or configuring CI/CD.

---

#### [GIT_WORKFLOW.md](GIT_WORKFLOW.md)
**Git Workflow & Branching Strategy**
- AI-optimized GitHub Flow (Claude Code + Cursor AI)
- Branch naming conventions
- Workflow for Claude Code development
- Workflow for Cursor development
- Emergency hotfix workflow
- AI code review process
- Branch protection rules
- Commit message conventions with AI tags
- Module dependencies & impact

**When to read**: Starting new features, creating branches, or reviewing code.

---

#### [SKILLS.md](SKILLS.md)
**Claude Skills Guide**
- env-health: Environment health checker
- env-switch: Environment switcher
- cache-guard: Laravel cache management
- db-validate: Database connection validator
- doc-sync: Documentation synchronization
- Skills usage tips and best practices

**When to read**: Troubleshooting environment issues, understanding automated skills, or managing configurations.

---

#### [TESTING.md](TESTING.md)
**Testing Strategy**
- Unit tests (business logic, calculations)
- Feature tests (API endpoints, database integration)
- E2E tests (complete user workflows)
- Test configuration (PHPUnit, Playwright)
- Running all tests
- Test best practices
- CI/CD integration
- Debugging failed tests
- Performance testing
- Coverage goals

**When to read**: Writing tests, debugging test failures, or understanding test strategy.

---

## 🚀 Quick Navigation

### By Task Type

**Setting Up Development Environment**
→ [DEPLOYMENT.md](DEPLOYMENT.md) (Local Development Environment section)

**Working on Sales Features**
→ [CALCULATIONS.md](CALCULATIONS.md) + [API_GUIDE.md](API_GUIDE.md)

**Creating a New Branch**
→ [GIT_WORKFLOW.md](GIT_WORKFLOW.md)

**Deploying to Production**
→ [DEPLOYMENT.md](DEPLOYMENT.md) (Railway Production Environment section)

**Writing Tests**
→ [TESTING.md](TESTING.md)

**Troubleshooting Environment Issues**
→ [SKILLS.md](SKILLS.md)

**Understanding API Endpoints**
→ [API_GUIDE.md](API_GUIDE.md)

---

## 📖 Main Documentation

For quick reference and core essentials, see: **[CLAUDE.md](../../CLAUDE.md)** (project root)

---

## 🔍 Search Across Documentation

**Looking for**:
- **Calculation formulas** → [CALCULATIONS.md](CALCULATIONS.md)
- **API endpoints** → [API_GUIDE.md](API_GUIDE.md)
- **Environment variables** → [DEPLOYMENT.md](DEPLOYMENT.md)
- **Git commands** → [GIT_WORKFLOW.md](GIT_WORKFLOW.md)
- **Test commands** → [TESTING.md](TESTING.md)
- **Claude Skills** → [SKILLS.md](SKILLS.md)

---

## 📝 Contributing to Documentation

When updating documentation:

1. **Update the relevant guide** in `docs/development/`
2. **Update CLAUDE.md** if it affects core essentials
3. **Test the changes** by asking Claude Code questions
4. **Commit with proper message**: `📝 docs: update [guide name]`

**Example**:
```bash
# After updating CALCULATIONS.md
git add docs/development/CALCULATIONS.md
git commit -m "📝 docs: clarify deduction calculation formula"
```

---

## 🛠️ Maintenance

This documentation structure was created to:
- ✅ Reduce CLAUDE.md size for better performance
- ✅ Organize documentation by topic
- ✅ Make it easier to find specific information
- ✅ Improve maintainability

**Optimization Date**: 2025-11-20

**Before**: CLAUDE.md (1,635 lines)
**After**: CLAUDE.md (271 lines) + 6 detailed guides = **83.4% reduction**

---

**Back to**: [CLAUDE.md](../../CLAUDE.md) | [Project Root](../../)

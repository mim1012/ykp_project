# Release Process Documentation

## 🚀 Release Management Guide

### Version: 1.0.0 (Genesis)
### Date: 2025-01-18

---

## 📋 Release Checklist

### Pre-Release (1 Week Before)
- [ ] Feature freeze announcement
- [ ] Complete all pending PRs
- [ ] Update documentation
- [ ] Security audit
- [ ] Performance testing
- [ ] Backup production database

### Release Preparation (2 Days Before)
- [ ] Update VERSION file
- [ ] Update CHANGELOG.md
- [ ] Update composer.json version
- [ ] Run full test suite
- [ ] Build production assets
- [ ] Create release branch

### Release Day
- [ ] Final testing on staging
- [ ] Create release tag
- [ ] Deploy to production
- [ ] Smoke test production
- [ ] Monitor error rates
- [ ] Announce release

### Post-Release (Next Day)
- [ ] Review metrics
- [ ] Address critical issues
- [ ] Update documentation
- [ ] Team retrospective
- [ ] Plan next release

---

## 🔢 Version Numbering

### Semantic Versioning Format
```
MAJOR.MINOR.PATCH[-PRERELEASE][+BUILD]

Examples:
1.0.0           - Production release
1.1.0-beta.1    - Beta release
1.1.0-rc.1      - Release candidate
1.0.1           - Patch release
2.0.0           - Major release (breaking changes)
```

### Version Increment Rules

| Change Type | Version Part | Example | When to Use |
|------------|--------------|---------|-------------|
| Breaking API changes | MAJOR | 1.0.0 → 2.0.0 | Incompatible changes |
| New features | MINOR | 1.0.0 → 1.1.0 | Backward compatible features |
| Bug fixes | PATCH | 1.0.0 → 1.0.1 | Backward compatible fixes |
| Pre-release | PRERELEASE | 1.1.0-beta.1 | Testing versions |

---

## 🔄 Release Workflow

### 1. Create Release Branch
```bash
# Start from develop branch
git checkout develop
git pull origin develop

# Create release branch
git checkout -b release/v1.1.0

# Update version files
echo "1.1.0" > VERSION
# Update composer.json, config/version.php
```

### 2. Update Version Files

#### VERSION file
```bash
echo "1.1.0" > VERSION
```

#### composer.json
```json
{
    "name": "ykp/dashboard",
    "version": "1.1.0"
}
```

#### config/version.php
```php
return [
    'current' => '1.1.0',
    'released_at' => '2025-01-18',
    'codename' => 'Genesis',
];
```

### 3. Update CHANGELOG.md
```markdown
## [1.1.0] - 2025-01-25

### Added
- New feature X
- Enhancement Y

### Fixed
- Bug fix A
- Issue B resolved

### Changed
- Updated dependency Z
```

### 4. Testing & Quality Assurance
```bash
# Run all tests
composer test

# Code quality checks
composer quality

# Security audit
composer audit

# Build assets
npm run build

# Test in staging environment
```

### 5. Create Release Tag
```bash
# Merge to main
git checkout main
git merge --no-ff release/v1.1.0

# Create tag
git tag -a v1.1.0 -m "Release version 1.1.0 - Codename"

# Push to remote
git push origin main
git push origin v1.1.0

# Merge back to develop
git checkout develop
git merge --no-ff release/v1.1.0
```

---

## 📦 Deployment Process

### Staging Deployment
```bash
# Deploy to staging
git checkout staging
git merge release/v1.1.0
git push origin staging

# Railway will auto-deploy staging branch
# Verify at: https://ykp-staging.railway.app
```

### Production Deployment
```bash
# After staging verification
git checkout main
git merge staging
git push origin main

# Railway will auto-deploy main branch
# Verify at: https://ykpproject-production.up.railway.app
```

### Rollback Process
```bash
# Quick rollback to previous version
git checkout main
git reset --hard v1.0.0
git push --force origin main

# Or use Railway's rollback feature
railway rollback
```

---

## 🔍 Release Validation

### Automated Tests
```yaml
# .github/workflows/release.yml
name: Release Validation
on:
  push:
    tags:
      - 'v*'

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - name: Run Tests
        run: composer test

      - name: Check Version
        run: |
          VERSION=$(cat VERSION)
          TAG=${GITHUB_REF#refs/tags/v}
          if [ "$VERSION" != "$TAG" ]; then
            echo "Version mismatch!"
            exit 1
          fi
```

### Manual Validation Checklist
```markdown
## Production Smoke Tests

### Authentication
- [ ] Admin login works
- [ ] User roles enforced
- [ ] Session management

### Core Features
- [ ] Sales calculation accurate
- [ ] Report generation works
- [ ] Data export functional
- [ ] API endpoints responding

### Performance
- [ ] Page load < 3 seconds
- [ ] API response < 1 second
- [ ] No memory leaks

### Monitoring
- [ ] Sentry receiving events
- [ ] Telescope accessible (HQ only)
- [ ] Logs being written
```

---

## 📊 Release Metrics

### Key Performance Indicators
```sql
-- Release success metrics
SELECT
    version,
    deploy_date,
    error_rate_24h,
    performance_score,
    user_satisfaction
FROM release_metrics
WHERE version = '1.1.0';
```

### Success Criteria
- Error rate < 0.1%
- Response time < 1s (p95)
- Zero critical bugs in 24h
- User satisfaction > 90%

---

## 📝 Release Notes Template

```markdown
# YKP Dashboard v1.1.0 Release Notes

## Release Date: January 25, 2025
## Codename: Evolution

### 🎉 New Features
- **Feature Name**: Description of the feature
- **Enhancement**: What was improved

### 🐛 Bug Fixes
- Fixed issue with [component]
- Resolved [specific bug]

### 💥 Breaking Changes
- API endpoint `/old` renamed to `/new`
- Configuration key `old_key` changed to `new_key`

### 📦 Dependencies
- Updated Laravel to 12.x
- Added Sentry for monitoring

### 📈 Performance Improvements
- 30% faster dashboard loading
- Reduced memory usage by 20%

### 🔒 Security Updates
- Patched CVE-2025-XXXX
- Enhanced authentication

### 📚 Documentation
- Updated API documentation
- Added troubleshooting guide

### 🙏 Contributors
- @developer1 - Feature X
- @developer2 - Bug fixes

### 📋 Full Changelog
See [CHANGELOG.md](./CHANGELOG.md) for detailed changes.

### 🚀 Upgrade Instructions
1. Backup database
2. Pull latest code
3. Run migrations: `php artisan migrate`
4. Clear cache: `php artisan cache:clear`
5. Build assets: `npm run build`
```

---

## 🔐 Security Considerations

### Pre-Release Security Checklist
- [ ] Dependency vulnerability scan
- [ ] OWASP Top 10 review
- [ ] Penetration testing (major releases)
- [ ] Code security audit
- [ ] Environment variable review

### Security Commands
```bash
# Check for vulnerable dependencies
composer audit
npm audit

# Static security analysis
./vendor/bin/phpstan analyse --level=max

# Check for exposed secrets
git secrets --scan
```

---

## 🚨 Emergency Release Process

### Hotfix Workflow
```bash
# Create hotfix from main
git checkout main
git checkout -b hotfix/critical-bug

# Fix the issue
# ... make changes ...

# Update patch version
echo "1.0.1" > VERSION

# Commit and tag
git add .
git commit -m "hotfix: Critical bug description"
git tag -a v1.0.1 -m "Hotfix v1.0.1"

# Deploy immediately
git checkout main
git merge hotfix/critical-bug
git push origin main --tags

# Merge back to develop
git checkout develop
git merge hotfix/critical-bug
```

### Communication Template
```
Subject: [URGENT] Hotfix v1.0.1 Deployed

Team,

We've deployed hotfix v1.0.1 to address a critical issue:

Issue: [Description]
Impact: [Who was affected]
Resolution: [What was fixed]
Status: RESOLVED

The fix has been deployed to production and verified.

Action Required: None
Monitoring: Enhanced monitoring active for 24h

Thanks,
DevOps Team
```

---

## 📅 Release Schedule

### Regular Releases
| Type | Frequency | Day | Time |
|------|-----------|-----|------|
| Major | Quarterly | First Monday | 02:00 UTC |
| Minor | Monthly | Second Tuesday | 02:00 UTC |
| Patch | As needed | Any | Any |
| Hotfix | Emergency | Any | ASAP |

### Release Calendar 2025
- **Q1**: v1.1.0 (Feb), v1.2.0 (Mar)
- **Q2**: v2.0.0 (Apr - Major), v2.1.0 (May), v2.2.0 (Jun)
- **Q3**: v2.3.0 (Jul), v2.4.0 (Aug), v2.5.0 (Sep)
- **Q4**: v3.0.0 (Oct - Major), v3.1.0 (Nov), v3.2.0 (Dec)

---

## 🛠️ Release Tools

### Automation Scripts
```bash
# scripts/release.sh
#!/bin/bash

VERSION=$1
CODENAME=$2

if [ -z "$VERSION" ]; then
    echo "Usage: ./release.sh VERSION [CODENAME]"
    exit 1
fi

echo "📦 Preparing release v$VERSION"

# Update version files
echo $VERSION > VERSION
sed -i "s/\"version\": \".*\"/\"version\": \"$VERSION\"/" composer.json
sed -i "s/'current' => '.*'/'current' => '$VERSION'/" config/version.php

# Update changelog
echo "## [$VERSION] - $(date +%Y-%m-%d) $CODENAME" >> CHANGELOG.md

# Run tests
composer test

# Create git tag
git add .
git commit -m "chore: Release v$VERSION"
git tag -a v$VERSION -m "Release v$VERSION - $CODENAME"

echo "✅ Release v$VERSION prepared!"
echo "📌 Next: Push to remote and deploy"
```

### GitHub Release Creation
```bash
# Create GitHub release
gh release create v1.1.0 \
    --title "v1.1.0 - Evolution" \
    --notes-file RELEASE_NOTES.md \
    --target main
```

---

## 📚 Resources

- [Semantic Versioning](https://semver.org/)
- [Keep a Changelog](https://keepachangelog.com/)
- [GitHub Releases](https://docs.github.com/en/repositories/releasing-projects-on-github)
- [Railway Deployment](https://docs.railway.app/)

---

**Document Version**: 1.0.0
**Last Updated**: 2025-01-18
**Next Review**: 2025-02-18
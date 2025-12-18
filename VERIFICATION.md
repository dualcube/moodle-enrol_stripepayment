# Verification Guide

This document helps you verify that all the fixes are working correctly.

## ✅ What Was Fixed

### 1. Zip File Location
- **Before**: Zip files were created but not saved in release folder
- **After**: Zip files are created and saved only in `release/` folder
- **Removed**: `dist/` folder is no longer used

### 2. Missing Moodle Plugin CI Checks
- **Added**: `moodle-plugin-ci phpcpd` (PHP Copy/Paste Detector)
- **Added**: `moodle-plugin-ci phpcs` (Moodle Code Checker)
- **Added**: `moodle-plugin-ci mustache` (Mustache template validation)
- **Added**: `moodle-plugin-ci grunt` (Grunt build validation)
- **Existing**: All other checks remain (phplint, phpmd, phpcbf, validate, savepoints, phpunit, behat)

## 🧪 Verification Steps

### Step 1: Verify Local Zip Creation

```bash
# Windows PowerShell
powershell -Command "if (!(Test-Path 'release')) { New-Item -ItemType Directory -Path 'release' }"
powershell -Command "Compress-Archive -Path . -DestinationPath release\stripepayment.zip -Force"

# Linux/macOS
mkdir -p release
zip -r release/stripepayment.zip . --exclude @.zipignore
```

**Expected Result**: `release/stripepayment.zip` should be created

### Step 2: Verify Composer Scripts

```bash
# Test zip creation (Windows)
composer run-script zip

# Test zip creation (Unix)
composer run-script zip-unix

# Test quality checks
composer run-script phpcs
composer run-script phpcf
composer run-script phpmd
```

**Expected Result**: All scripts should run without errors

### Step 3: Verify Local Check Scripts

```bash
# Windows Batch
run-local-checks.bat

# Windows PowerShell
powershell -ExecutionPolicy Bypass -File run-local-checks.ps1

# Linux/macOS
chmod +x run-local-checks.sh
./run-local-checks.sh
```

**Expected Result**: Scripts should run all checks and create zip in release folder

### Step 4: Verify GitHub Workflow

1. **Push changes** to your repository
2. **Check Actions tab** in GitHub
3. **Verify workflow runs** all the new checks:
   - PHP Lint
   - PHP Copy/Paste Detector
   - PHP Mess Detector
   - Moodle Code Checker
   - PHP Code Beautifier and Fixer
   - Validate Plugin
   - Check Mustache Templates
   - Check Grunt Build
   - Check Upgrade Savepoints
   - PHPUnit Tests
   - Run Behat Tests

**Expected Result**: All checks should pass (or show expected warnings)

### Step 5: Verify Release Process

1. **Create a tag**:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

2. **Check GitHub Releases** page
3. **Verify release is created** with `stripepayment.zip` attached

**Expected Result**: GitHub release should be automatically created with zip file

## 🔍 Troubleshooting

### Issue: Zip not created
**Solution**: Check if you have zip utility (Linux/macOS) or PowerShell (Windows)

### Issue: Composer scripts fail
**Solution**: Run `composer install` to install dependencies

### Issue: Local checks fail
**Solution**: Install PHP, Node.js, and Composer in your PATH

### Issue: Workflow fails on new checks
**Solution**: Fix the code issues reported by the new checks:
- `phpcpd`: Remove duplicate code
- `phpcs`: Fix coding standards
- `mustache`: Fix template syntax
- `grunt`: Fix build issues

## 📋 Checklist

- [ ] `release/stripepayment.zip` is created locally
- [ ] `composer run-script zip` works
- [ ] Local check scripts run successfully
- [ ] GitHub workflow includes all moodle-plugin-ci checks
- [ ] No more `dist/` folder references
- [ ] Release job creates GitHub releases on tags
- [ ] All quality checks pass

## 🎯 Expected File Structure

```
stripepayment/
├── .github/workflows/moodle-ci.yml  ✅ Updated with all checks
├── release/stripepayment.zip        ✅ Created by scripts
├── run-local-checks.sh              ✅ Linux/macOS script
├── run-local-checks.bat             ✅ Windows batch script
├── run-local-checks.ps1             ✅ Windows PowerShell script
├── composer.json                    ✅ Updated scripts
├── LOCAL_DEVELOPMENT.md             ✅ Documentation
├── FIXES_SUMMARY.md                 ✅ Summary of changes
└── VERIFICATION.md                  ✅ This file
```

## 🚀 Next Steps

1. **Test locally** using the verification steps above
2. **Push changes** to trigger the workflow
3. **Create a tag** to test the release process
4. **Monitor** the GitHub Actions for any issues
5. **Fix** any code quality issues reported by the new checks

The workflow now includes all standard moodle-plugin-ci checks and properly manages zip files in the release folder only.

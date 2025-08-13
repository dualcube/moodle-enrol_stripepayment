#!/bin/bash

# Local Moodle Plugin CI Checks Script
# This script runs the same checks as the GitHub workflow locally

set -e

echo "🚀 Starting local Moodle Plugin CI checks..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "version.php" ]; then
    print_error "version.php not found. Please run this script from the plugin root directory."
    exit 1
fi

# Install dependencies if needed
print_status "Installing dependencies..."
if [ ! -d "vendor" ]; then
    composer install
fi

if [ ! -d "node_modules" ]; then
    npm install
fi

# Build assets
print_status "Building assets..."
npm run build

# Run PHP Lint
print_status "Running PHP Lint..."
if command -v php >/dev/null 2>&1; then
    find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" -exec php -l {} \; > /dev/null
    print_success "PHP Lint passed"
else
    print_warning "PHP not found, skipping PHP Lint"
fi

# Run PHP CodeSniffer
print_status "Running PHP CodeSniffer..."
if [ -f "vendor/bin/phpcs" ]; then
    composer run-script phpcs || print_warning "PHPCS found issues"
    print_success "PHPCS check completed"
else
    print_warning "PHPCS not found, install dev dependencies with: composer install"
fi

# Run PHP Code Beautifier and Fixer
print_status "Running PHP Code Beautifier and Fixer..."
if [ -f "vendor/bin/phpcbf" ]; then
    composer run-script phpcf || print_warning "PHPCBF made changes"
    print_success "PHPCBF check completed"
else
    print_warning "PHPCBF not found, install dev dependencies with: composer install"
fi

# Run PHP Mess Detector (if available)
print_status "Running PHP Mess Detector..."
if [ -f "vendor/bin/phpmd" ]; then
    composer run-script phpmd || print_warning "PHPMD found issues"
    print_success "PHPMD check completed"
else
    print_warning "PHPMD not found, install dev dependencies with: composer install"
fi

# Validate plugin structure
print_status "Validating plugin structure..."
required_files=("version.php" "lib.php" "lang/en/enrol_stripepayment.php")
for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        print_error "Required file missing: $file"
        exit 1
    fi
done
print_success "Plugin structure validation passed"

# Check version.php format
print_status "Checking version.php format..."
if grep -q "plugin->version" version.php && grep -q "plugin->component" version.php; then
    print_success "version.php format is correct"
else
    print_error "version.php format is incorrect"
    exit 1
fi

# Build zip package
print_status "Building zip package..."
composer run-script zip-release
if [ -f "release/stripepayment.zip" ]; then
    print_success "Zip package created: release/stripepayment.zip"
else
    print_error "Failed to create zip package"
    exit 1
fi

# Final summary
echo ""
echo "🎉 Local checks completed!"
echo ""
echo "📦 Package: release/stripepayment.zip"
echo ""
echo "To install moodle-plugin-ci for more comprehensive testing:"
echo "  composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ci ^4"
echo "  export PATH=\$PATH:\$(pwd)/ci/bin:\$(pwd)/ci/vendor/bin"
echo "  moodle-plugin-ci install --plugin . --db-host=127.0.0.1"
echo ""
echo "Then run additional checks:"
echo "  moodle-plugin-ci phplint ."
echo "  moodle-plugin-ci phpcpd ."
echo "  moodle-plugin-ci phpmd ."
echo "  moodle-plugin-ci phpcs ."
echo "  moodle-plugin-ci phpcbf ."
echo "  moodle-plugin-ci validate ."
echo "  moodle-plugin-ci mustache ."
echo "  moodle-plugin-ci grunt ."
echo "  moodle-plugin-ci savepoints ."
echo "  moodle-plugin-ci phpunit ."
echo "  moodle-plugin-ci behat ."

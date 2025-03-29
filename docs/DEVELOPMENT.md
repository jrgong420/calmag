# Development Guide

## Prerequisites

- PHP >= 8.1
- Composer
- Node.js >= 14.x
- npm >= 6.x

## Setup

1. Clone the repository:
```bash
git clone https://github.com/webklex/calmag.git
cd calmag
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install JavaScript dependencies:
```bash
npm install
```

4. Build assets:
```bash
npm run production
npm run build:tailwind
```

5. Start the development server:
```bash
php -S localhost:8000 -t public
```

## Project Structure

```
calmag/
├── public/           # Public assets and entry point
├── resources/        # Frontend resources
│   ├── css/         # CSS files
│   ├── js/          # JavaScript files
│   └── lang/        # Language files
├── src/             # PHP source code
│   ├── config/      # Configuration files
│   ├── Helper/      # Helper functions
│   └── Enums/       # Enumeration classes
├── tests/           # Test files
└── docs/            # Documentation
```

## Development Workflow

1. Create a new branch for your feature:
```bash
git checkout -b feature/your-feature-name
```

2. Make your changes following the coding standards:
   - Use PSR-12 coding style
   - Add type hints
   - Document your code
   - Write tests for new features

3. Run tests:
```bash
./vendor/bin/phpunit
```

4. Build assets:
```bash
npm run production
```

5. Commit your changes:
```bash
git add .
git commit -m "Description of your changes"
```

6. Push to your branch:
```bash
git push origin feature/your-feature-name
```

7. Create a Pull Request

## Adding New Features

### Adding a New Fertilizer

1. Edit `src/config/fertilizers.php`:
```php
'new_fertilizer' => [
    'name' => 'New Fertilizer',
    'description' => 'Description of the fertilizer',
    'ca_percentage' => 15.0,
    'mg_percentage' => 5.0,
    // ... other properties
]
```

### Adding a New Additive

1. Edit `src/config/additives.php`:
```php
'new_additive' => [
    'name' => 'New Additive',
    'description' => 'Description of the additive',
    'ca_percentage' => 10.0,
    'mg_percentage' => 0.0,
    'recommended_dosage' => [
        'min' => 1,
        'max' => 3,
        'unit' => 'g/L'
    ]
]
```

### Adding a New Language

1. Create a new language file in `resources/lang/`:
```php
return [
    'calculator' => [
        'title' => 'Calculator Title',
        // ... other translations
    ]
];
```

## Testing

### Writing Tests

1. Create a new test file in `tests/`:
```php
<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class YourTest extends TestCase
{
    public function testYourFeature()
    {
        // Your test code
    }
}
```

2. Run tests:
```bash
./vendor/bin/phpunit
```

## Code Style

- Follow PSR-12 coding standards
- Use type hints for all method parameters and return types
- Document all public methods and classes
- Keep methods focused and single-purpose
- Use meaningful variable and method names

## Debugging

1. Enable error reporting in `public/index.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

2. Use Xdebug for step debugging:
   - Install Xdebug
   - Configure your IDE
   - Set breakpoints
   - Start debugging session

## Performance Optimization

1. Asset Optimization:
   - Minify CSS and JavaScript
   - Optimize images
   - Use CDN for assets

2. PHP Optimization:
   - Enable OPcache
   - Use proper caching
   - Optimize database queries

## Deployment

1. Build production assets:
```bash
npm run production
```

2. Deploy to production server:
```bash
git push production main
```

3. Run database migrations:
```bash
php artisan migrate
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Support

- Create an issue for bugs
- Join the discussion for features
- Contact maintainers for urgent issues

# Contributing to the CalMag Calculator

## Getting Started

If you're interested in helping improve the CalMag Calculator, this guide will help you get started. Whether you're a developer or just want to help with documentation, there are many ways to contribute.

## Ways to Contribute

### 1. Testing and Feedback
- Try out the calculator
- Report any issues you find
- Suggest improvements
- Share your experience

### 2. Adding New Products
You can help add new fertilizers or additives to the calculator. You'll need:
- Product name and description
- Calcium and magnesium percentages
- Recommended usage amounts
- Safety information

### 3. Improving Documentation
Help make the documentation better by:
- Finding unclear explanations
- Suggesting better wording
- Adding examples
- Fixing typos

### 4. Adding New Languages
Help make the calculator available in more languages by:
- Translating the interface
- Providing language files
- Testing translations
- Suggesting improvements

## Technical Contributions

If you're comfortable with coding, you can help with:

### Adding New Features
1. Create a new branch for your work
2. Make your changes
3. Test everything works
4. Submit your changes for review

### Fixing Issues
1. Find a bug you want to fix
2. Create a fix
3. Test your solution
4. Submit the fix for review

## Getting Help

Need assistance? You can:
- Ask questions in the issue tracker
- Join the discussion forum
- Contact the maintainers
- Check the FAQ

## Code Standards

When contributing code:
- Follow the existing style
- Add helpful comments
- Test your changes
- Keep things simple

## Testing Your Changes

Before submitting changes:
1. Make sure everything works
2. Test different scenarios
3. Check for errors
4. Verify on different devices

## Submitting Changes

To submit your changes:
1. Create a pull request
2. Explain what you changed
3. Include any relevant information
4. Wait for review

## Support

Need help? You can:
- Create an issue
- Join discussions
- Contact the team
- Check the documentation

## Thank You!

Your contributions help make the CalMag Calculator better for everyone. Thank you for helping! 
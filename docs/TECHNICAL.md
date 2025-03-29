# Technical Overview

## How the Calculator Works

The CalMag Calculator is built with several key components that work together to help you calculate nutrient solutions. Here's a simple explanation of how everything fits together:

### Main Components

1. **Calculator Engine** (`Calculator.php`)
   - The brain of the application
   - Handles all the math for nutrient calculations
   - Makes sure your nutrient solutions are balanced correctly

2. **User Interface** (`Controller.php`)
   - Handles what you see on the screen
   - Makes sure your inputs are valid
   - Shows you the results in an easy-to-understand way

3. **Settings Manager** (`Config.php`)
   - Stores all the information about fertilizers and additives
   - Keeps track of your preferences
   - Makes sure everything is set up correctly

4. **Language Support** (`Translator.php`)
   - Makes the calculator available in different languages
   - Automatically detects your preferred language
   - Ensures consistent translations

### How Information Flows

1. You enter your requirements (water volume, target concentrations)
2. The system checks if your inputs make sense
3. The calculator performs the necessary calculations
4. You get your results with recommendations

### Safety Features

The calculator includes several safety measures:
- Checks for invalid inputs before calculating
- Warns you about potential issues
- Prevents harmful combinations of nutrients
- Keeps your data secure

### Data Storage

Your information is handled securely:
- No sensitive data is stored permanently
- Calculations are done in real-time
- Your browser's security features are respected

## Technical Requirements

To run the calculator, you need:
- A modern web browser (Chrome, Firefox, Safari, or Edge)
- An internet connection
- JavaScript enabled in your browser

## Privacy and Security

The calculator:
- Doesn't store your personal information
- Doesn't track your usage
- Doesn't share your data with third parties
- Uses secure connections for all communications

# Technical Documentation

## Project Structure

```
src/
├── Application.php      # Main application bootstrap
├── Calculator.php       # Core calculation logic
├── Comparator.php       # Comparison functionality
├── Config.php          # Configuration management
├── Controller.php      # Request handling and routing
├── Translator.php      # Internationalization
├── config/            # Configuration files
├── Helper/            # Helper functions
└── Enums/             # Enumeration classes
```

## Core Components

### Application
The `Application` class serves as the main bootstrap for the application. It handles:
- Initialization of core services
- Configuration loading
- Request processing
- Response generation

### Calculator
The `Calculator` class is the heart of the application, responsible for:
- Calcium and magnesium concentration calculations
- Nutrient solution optimization
- Formula processing
- Result validation

### Controller
The `Controller` class manages:
- Request routing
- Input validation
- Response formatting
- Error handling

### Configuration
The application uses several configuration files:
- `app.php`: Core application settings
- `additives.php`: Available nutrient additives
- `fertilizers.php`: Supported fertilizer types

## Data Flow

1. Request received by `Controller`
2. Input validated and processed
3. `Calculator` performs calculations
4. Results formatted and returned

## Error Handling

The application implements comprehensive error handling:
- Input validation errors
- Calculation errors
- Configuration errors
- System errors

## Internationalization

The application supports multiple languages through:
- Language files in `resources/lang`
- `Translator` class for text processing
- Browser language detection

## Security Considerations

- Input sanitization
- XSS prevention
- CSRF protection
- Secure configuration handling 
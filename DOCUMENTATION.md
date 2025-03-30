# CalMag Calculator Documentation

## 1. Introduction

This project is a web-based Calcium (Ca) and Magnesium (Mg) calculator designed primarily for plant cultivation. It helps users determine the correct amounts of fertilizers and additives needed to achieve a desired Ca:Mg ratio and target nutrient levels (in mg/L or ppm) in their water source.

The calculator considers:
*   The initial mineral content of the user's water source.
*   The nutrient profile of a selected base fertilizer.
*   The nutrient profile and concentration of selected Calcium and Magnesium additives.
*   The desired final Ca:Mg ratio.
*   Target nutrient levels for different plant growth stages (Propagation, Vegetation, Flower, Late Flower).
*   The volume of water being treated.

## 2. Features

*   **Standard Calculator**: Calculates additive amounts based on predefined fertilizers and additives from configuration files.
*   **Expert Builder**: Allows users to define custom fertilizer and additive compositions and set specific target nutrient levels (Ca & Mg ppm) for each week of different grow stages.
*   **Comparison Tool**: Compares how different base fertilizers affect the final nutrient mix when aiming for the target ratio with the user's water.
*   **Multi-language Support**: Currently supports English (en) and German (de), detected via session or browser settings.
*   **Unit Handling**: Primarily works with mg/L (ppm), but input can sometimes be mmol/L. Handles US (Gallons) and EU (Liters) volume units based on region selection.
*   **Water Dilution**: Can calculate the necessary dilution with pure water (like RO water) if the source water's initial mineral content is too high.
*   **API Access**: Provides a JSON API for programmatic calculations.

## 3. Setup and Running Locally

Follow these steps to set up and run the project on your local machine.

**Prerequisites:**

*   **PHP**: Version 8.1 or higher recommended.
*   **Composer**: PHP dependency manager. ([Installation Guide](https://getcomposer.org/doc/00-intro.md))

**Steps:**

1.  **Get the Code**: Download or clone the project repository to your local machine.
2.  **Navigate to Project Directory**: Open your terminal or command prompt and navigate into the project's root directory (`calmag`).
    ```bash
    cd path/to/calmag
    ```
3.  **Install Dependencies**: Run Composer to install the necessary PHP libraries (like Symfony components used for translation).
    ```bash
    composer install
    ```
    This will create a `vendor` directory containing the dependencies.
4.  **Start the Development Server**: Use PHP's built-in web server to run the application. The `-t public` flag tells the server to use the `public` directory as the document root, which is essential for the application to work correctly.
    ```bash
    php -S localhost:8000 -t public
    ```
    *Alternatively*, if you are using Visual Studio Code, a task has been configured:
    *   Open the Command Palette (Cmd+Shift+P on macOS, Ctrl+Shift+P on Windows/Linux).
    *   Type "Tasks: Run Task" and select it.
    *   Choose "Start PHP Dev Server".

5.  **Access the Application**: Open your web browser and go to `http://localhost:8000`.

## 4. Project Structure

*   **`src/`**: Contains the core PHP classes (logic).
    *   `config/`: Configuration files for fertilizers, additives, application settings, etc.
    *   `Enums/`: PHP enumerations (e.g., `GrowState`).
    *   `Helper/`: Utility helper functions.
    *   `Application.php`: Handles request routing.
    *   `Controller.php`: Manages application flow and data for views.
    *   `Calculator.php`: Core calculation logic.
    *   `Comparator.php`: Logic for the comparison tool.
    *   `Config.php`: Loads and provides access to configuration files.
    *   `Translator.php`: Handles language translations.
*   **`public/`**: Web server document root. Contains the entry point (`index.php`) and publicly accessible assets.
    *   `css/`: Compiled CSS files.
    *   `fonts/`: Web fonts.
    *   `images/`: Image assets.
    *   `index.php`: Main entry point, bootstraps the application.
    *   `mix-manifest.json`: Used by Laravel Mix for asset versioning.
*   **`resources/`**: Source files before compilation/processing.
    *   `css/`: Source CSS files (Tailwind).
    *   `lang/`: Language translation files (`en.php`, `de.php`).
    *   `views/`: PHP template files (`.phtml`) for rendering HTML pages.
*   **`vendor/`**: Contains dependencies installed by Composer.
*   **`DOCUMENTATION.md`**: This file.
*   **`composer.json`**: Defines project dependencies for Composer.
*   **`package.json`**, **`webpack.mix.js`**, **`tailwind.config.js`**: Configuration for frontend asset bundling (CSS/JS) using Laravel Mix and Tailwind CSS (though JS usage seems minimal).

## 5. Core Classes Overview

*   **`Application`**: The entry point after `public/index.php`. It inspects the incoming request (URL, method, parameters) and routes it to the appropriate method in the `Controller`. Handles special routes like `/switch-language` and API calls.
*   **`Controller`**: Acts as the orchestrator. It receives requests from `Application`, validates input data using the `validate` method, interacts with the `Calculator` or `Comparator` to perform logic, prepares data, and then renders the appropriate HTML view (`.phtml` file) using the `render` method.
*   **`Calculator`**: The heart of the application. It takes water composition, selected fertilizer/additives, and target ratios/levels, then calculates the required amounts (ml/L or mg/L) of each component. It handles complex logic like water dilution and iterative adjustments to reach targets. The `calculateFertilizer` method performs calculations for a single target, while `generateResultTable` does it week-by-week.
*   **`Comparator`**: Used for the comparison tool. It takes the user's water data and runs the `Calculator` logic for *each* available fertilizer to show how they perform in reaching the targets.
*   **`Config`**: A simple utility class (singleton) to load configuration arrays from files in `src/config/` (e.g., `app.php`, `fertilizers.php`). Allows accessing config values using dot notation (e.g., `Config::get('app.name')`).
*   **`Translator`**: Manages multi-language support. It loads translation strings from files in `resources/lang/` based on the detected locale (session or browser). Provides the `translate()` static method (or `get()` instance method) to retrieve translated strings, supporting placeholder replacement.

## 6. Configuration

Key application settings, fertilizer data, and additive data are stored in PHP files within the `src/config/` directory:

*   **`app.php`**: General application settings, default elements, available regions, target levels for grow stages.
*   **`fertilizers.php`**: Defines available base fertilizers, grouped by brand, including their element composition (often N-P-K, Ca, Mg percentages) and density.
*   **`additives.php`**: Defines available Calcium and Magnesium additives, including their element composition, concentration (if liquid), and density.

These files return PHP arrays, making them easy to modify or extend with new data.

## 7. Usage Guide

*   **Main Calculator (Home Page)**:
    *   Enter your source water's mineral content (Ca, Mg, etc.) in mg/L (ppm).
    *   Select your desired region (US Gallons or EU Liters) for volume input.
    *   Select a base fertilizer from the dropdown.
    *   Select Calcium and Magnesium additives. If they are liquids, specify their concentration.
    *   Enter the target water volume.
    *   Adjust the desired Ca:Mg ratio if needed.
    *   Optionally adjust the target offset percentage.
    *   Submit the form to see the calculated results table, showing weekly additions.
*   **Expert Mode**: Append `?expert=1` to the URL. This reveals more input fields allowing you to:
    *   Define custom fertilizer and additive compositions (% elements).
    *   Set specific target Ca and Mg levels (ppm) and duration (weeks) for each grow stage (Propagation, Vegetation, Flower, Late Flower).
*   **Comparison Tool**: Append `?compare=1` to the URL. Enter your water details and target ratio. Submitting will show how *all* configured fertilizers perform in reaching the targets with your water.
*   **Builder Tool**: Append `?builder=1` to the URL. This seems similar to Expert Mode, focusing on building custom nutrient solutions.
*   **Language Switcher**: The application attempts to detect your language. You can manually switch between available languages (EN/DE) using the language selector usually found in the header or footer. This makes a POST request to `/switch-language`.
*   **API**: Send a POST request to the root URL (`/`) with `Content-Type: application/json` and a JSON body containing the same parameters as the main form (`fertilizer`, `additive`, `ratio`, `volume`, `elements`, etc.). The response will be JSON containing the calculation result or an error.

## 8. For Non-Programmers: What Does This Tool Do?

Imagine you're feeding plants. Plants need specific nutrients, especially Calcium (Ca) and Magnesium (Mg), in the right balance (ratio) to grow well. Tap water already contains some minerals, and fertilizers add more, but often the Ca:Mg balance isn't ideal.

This tool helps you figure out:

1.  **What's in your water?** You tell the tool the Ca and Mg levels (usually from a water report).
2.  **What fertilizer are you using?** You select your fertilizer from a list.
3.  **What's the goal?** You set a target Ca:Mg ratio (e.g., 3.5 parts Ca for every 1 part Mg) and target levels for different growth stages.
4.  **How much additive is needed?** The tool calculates exactly how much Calcium additive (like Cal-Mag supplement) and/or Magnesium additive (like Epsom salt) you need to add to your water *along with* your base fertilizer to hit the target ratio and levels perfectly for each week of the plant's life.

It even considers if your starting water has *too much* Ca or Mg and suggests how much pure water (like Reverse Osmosis water) to mix in (dilution). The "Expert Mode" lets advanced users define their own custom fertilizers and targets.

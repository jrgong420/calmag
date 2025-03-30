<?php

namespace Webklex\CalMag;

use Exception;
use Webklex\CalMag\Enums\GrowState;

/**
 * Class Controller
 *
 * Handles the application logic for different views/actions like the main calculator,
 * comparison tool, and the builder. It interacts with the Calculator and Comparator
 * classes, validates user input, and prepares data for rendering views.
 *
 * @package Webklex\CalMag
 */
class Controller {

    /** @var Calculator The main calculator instance for nutrient calculations. */
    private Calculator $calculator;

    /** @var array Current water element composition (e.g., calcium, magnesium levels). */
    private array $elements;

    /** @var array List of elements available for user input/display (can differ for expert mode). */
    protected array $available_elements;

    /** @var array Default units for elements (primarily mg/L). */
    protected array $element_units = [
        "calcium"   => "mg",
        "magnesium" => "mg",
        "potassium" => "mg",
        "iron"      => "mg",
        "sulphate"  => "mg",
        "nitrate"   => "mg",
        "nitrite"   => "mg",
    ];

    /** @var string The currently selected base fertilizer name. */
    private string $fertilizer = "";
    /** @var array The currently selected additives (one for magnesium, one for calcium). */
    private array $additive = [
        "magnesium" => "",
        "calcium"   => "",
    ];
    /** @var array Concentration values for selected additives (used if additives are liquids/ml). */
    private array $additive_concentration;
    /** @var array Units for selected additives (mg or ml). */
    private array $additive_units;
    /** @var float The target Calcium to Magnesium ratio. */
    private float $ratio = 3.5;
    /** @var float The density of the fertilizer/additive (used in builder mode, typically 1.0). */
    private float $density = 1.0;

    /** @var float The volume of water for the calculation (e.g., in Liters or Gallons depending on region). */
    private float $volume = 5.0;
    /** @var bool Whether to support dilution calculations (adjusting for existing water elements). */
    private bool $support_dilution = true;
    /** @var float A percentage offset applied to the target nutrient levels. */
    private float $target_offset = 0.0;

    /** @var string The selected region ('us' or 'eu'), affecting units (Gallons/Liters). */
    private string $region = "us";

    /** @var array Available regions loaded from config. */
    private array $regions;

    // --- Properties primarily used in Expert Mode ---
    /** @var array Custom element composition defined for additives. */
    private array $additive_elements = [];
    /** @var array Custom element composition defined for the base fertilizer. */
    private array $fertilizer_elements = [];
    /** @var array Target number of weeks for each grow state. */
    private array $target_weeks = [];
    /** @var array Target calcium levels (ppm) for each grow state. */
    private array $target_calcium = [];
    /** @var array Target magnesium levels (ppm) for each grow state. */
    private array $target_magnesium = [];
    // --- End Expert Mode Properties ---

    /** @var bool Flag indicating if the current request payload has been successfully validated. */
    private bool $validated = false;

    /**
     * Controller constructor.
     * Loads initial configuration (elements, regions), sets the default region based on translation,
     * checks for expert mode, and initializes the Calculator instance.
     */
    public function __construct() {
        // Load base elements and available regions from configuration files.
        $this->elements = Config::get("app.elements");
        $this->regions = Config::get("app.regions");
        $this->available_elements = Config::get("app.available_elements"); // Standard elements shown

        // Set default region based on translated default value.
        $region = Translator::translate("region.default");
        if (isset($this->regions[$region])) {
            $this->region = $region;
        }

        // Check if 'expert' mode is activated via GET parameter.
        if (($_GET["expert"] ?? null)) {
            // Load the extended list of elements for expert mode.
            $this->available_elements = Config::get("app.expert_elements");
        }

        // Initialize the main calculator service.
        $this->loadCalculator();
    }

    /**
     * Load the Calculator instance.
     * Initializes the Calculator with default water elements, fertilizer, additives, and ratio.
     * Also sets the default additive concentrations and units based on the first available additive
     * of each type (calcium/magnesium) from the configuration.
     *
     * @return void
     */
    private function loadCalculator(): void {
        // Create a new Calculator instance.
        $this->calculator = new Calculator(["elements" => $this->elements], $this->fertilizer, $this->additive, $this->ratio);

        // Get the list of available additives from the calculator (loaded from config).
        $additives = $this->calculator->getAdditives();

        // Set default concentrations based on the first listed additive for each element.
        $this->additive_concentration = [
            "magnesium" => $additives["magnesium"][array_key_first($additives["magnesium"])]["concentration"],
            "calcium"   => $additives["calcium"][array_key_first($additives["calcium"])]["concentration"],
        ];
        // Set default units based on the first listed additive for each element (defaulting to 'mg').
        $this->additive_units = [
            "magnesium" => $additives["magnesium"][array_key_first($additives["magnesium"])]["unit"] ?? "mg",
            "calcium"   => $additives["calcium"][array_key_first($additives["calcium"])]["unit"] ?? "mg",
        ];
    }

    /**
     * Render the main calculator page (index).
     * Prepares default form values based on the first available fertilizer and additives.
     * Passes necessary data (form defaults, regions, available elements, calculator instance) to the view.
     *
     * @return void
     */
    public function index(): void {
        $this->render(function() {
            // Get available fertilizers and additives from the calculator.
            $fertilizers = $this->calculator->getFertilizers();
            $additives = $this->calculator->getAdditives();

            // Prepare default form values.
            $form = [
                "fertilizer"             => array_key_first($fertilizers), // Default to first fertilizer
                "additive"               => [
                    "magnesium" => array_key_first($additives["magnesium"]), // Default to first Mg additive
                    "calcium"   => array_key_first($additives["calcium"]),   // Default to first Ca additive
                ],
                "ratio"                  => $fertilizers[array_key_first($fertilizers)]["ratio"], // Default ratio from first fertilizer
                "volume"                 => $this->volume, // Default volume
                "support_dilution"       => $this->support_dilution, // Default dilution setting
                "target_offset"          => $this->target_offset, // Default target offset
                "region"                 => $this->region, // Default region
                "elements"               => $this->elements, // Default water elements
                "element_units"          => $this->element_units, // Default element units
                "additive_concentration" => $this->additive_concentration, // Default additive concentrations
                "additive_units"         => $this->additive_units, // Default additive units
            ];

            // Return data to be extracted in the view.
            return [
                "form"               => $form, // Form defaults
                "regions"            => $this->regions, // Available regions
                "available_elements" => $this->available_elements, // Elements to display in form
                "calculator"         => $this->calculator, // Calculator instance (for accessing fertilizers/additives in view)
            ];
        }); // Renders the 'calculator.phtml' view by default.
    }

    /**
     * Render the calculator result page.
     * First, validates the incoming payload (from form submission or shared link).
     * If validation fails, it redirects back to the index page.
     * If validation succeeds, it prepares form data (using submitted values) and calculates the result.
     * Passes form data, calculation result, regions, elements, and calculator instance to the view.
     *
     * @param array $payload The request payload (usually from $_POST or decoded from $_GET['p']).
     * @return void
     */
    public function result(array $payload): void {
        try {
            // Validate the incoming data and update controller properties.
            $this->validate($payload);
        } catch (Exception $e) {
            // If validation fails, mark as not validated and render the index page again.
            $this->validated = false;
            $this->index(); // Show the form again (potentially with error messages if implemented)
            return; // Stop execution
        }

        // Render the result view.
        $this->render(function() {
            // Prepare form data based on validated controller properties.
            $form = [
                "fertilizer"             => $this->fertilizer !== "" ? $this->fertilizer : $this->calculator->getFertilizer(),
                "additive"               => count($this->additive) > 0 ? $this->additive : $this->calculator->getAdditive(),
                "ratio"                  => $this->ratio,
                "volume"                 => $this->volume,
                "support_dilution"       => $this->support_dilution,
                "target_offset"          => $this->target_offset,
                "region"                 => $this->region,
                "elements"               => $this->elements, // Water elements (potentially converted)
                "element_units"          => $this->element_units, // Original units submitted
                "show_suggestions"       => (bool)($_GET["show_suggestions"] ?? false), // Flag for alternative suggestions
                // Include expert mode fields if they were submitted/validated
                "additive_concentration" => $this->additive_concentration,
                "additive_units"         => $this->additive_units,
                "additive_elements"      => $this->additive_elements,
                "fertilizer_elements"    => $this->fertilizer_elements,
                "target_weeks"           => $this->target_weeks,
                "target_calcium"         => $this->target_calcium,
                "target_magnesium"       => $this->target_magnesium,
            ];

            // Return data for the view.
            return [
                "form"               => $form, // Data to repopulate the form
                "result"             => $this->validated ? $this->calculator->calculate() : null, // Calculation result (or null if validation failed somehow)
                "regions"            => $this->regions,
                "available_elements" => $this->available_elements,
                "calculator"         => $this->calculator,
            ];
        }); // Renders 'calculator.phtml' view by default.
    }

    /**
     * Render the comparison page.
     * This tool compares the Calcium/Magnesium ratio of the provided water elements.
     * It takes 'elements' and 'element_units' from the payload.
     *
     * @param array $payload Request payload containing 'elements' and 'element_units'.
     * @return void
     */
    public function compare(array $payload): void {
        // Define the elements relevant for comparison.
        $valid_elements = ["calcium", "magnesium"];
        // Get elements and units from payload, falling back to controller defaults.
        $_elements = $payload['elements'] ?? $this->elements;
        $_element_units = $payload['element_units'] ?? $this->element_units;

        // Ensure elements and units are arrays.
        if (!is_array($_elements)) {
            $_elements = $this->elements;
        }
        if (!is_array($_element_units)) {
            $_element_units = $this->element_units;
        }

        // Sanitize and prepare the elements and units for comparison.
        $elements = [];
        $element_units = [];
        foreach ($valid_elements as $element) {
            // Ensure calcium and magnesium exist, default to 0 if not.
            if (!isset($_elements[$element])) {
                $_elements[$element] = 0;
            }
            // Ensure units exist, default to 'mg'.
            if (!isset($_element_units[$element])) {
                $_element_units[$element] = "mg";
            }
            // Convert value to float.
            $elements[$element] = (float)$_elements[$element];
            // Sanitize unit (allow only 'ml' or 'mg', default to 'mg').
            $element_units[$element] = match (strtolower($_element_units[$element])) {
                "ml", "mg" => $_element_units[$element],
                default => "mg",
            };
        }

        // Convert the sanitized elements to mg/L using the conversion helper.
        // Merge with existing controller elements to ensure all necessary data is present.
        $this->elements = $this->convertElements(array_merge($this->elements, $elements), array_merge($this->element_units, $element_units));

        // Create a Comparator instance with the converted water elements and target ratio.
        $comparator = new Comparator($this->elements, $this->ratio);

        // Mark as validated if it's a POST request (form submission).
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->validated = true;
        }

        // Render the comparison view.
        $this->render(function() use ($comparator) {
            // Prepare form data.
            $form = [
                "ratio"         => $this->ratio,
                "elements"      => $this->elements, // Pass converted elements
                "element_units" => $this->element_units, // Pass original units
            ];
            // Return data for the view.
            return [
                "form"               => $form,
                "result"             => $this->validated ? $comparator->calculate() : null, // Calculate result if validated
                "available_elements" => $this->available_elements, // Elements for display
                "comparator"         => $comparator, // Comparator instance
            ];
        }, "compare"); // Specify the 'compare.phtml' view.
    }

    /**
     * Render the fertilizer builder page (Expert Mode).
     * Allows users to define custom fertilizers/additives and target nutrient levels per grow stage.
     * Validates the complex payload required for this mode.
     *
     * @param array $payload Request payload containing detailed custom fertilizer/additive/target data.
     * @return void
     */
    public function builder(array $payload): void {
        try {
            // Validate the payload (this method handles expert mode specifics).
            $this->validate($payload);
        } catch (Exception $e) {
            // Mark as invalid if validation fails.
            $this->validated = false;
        }

        // Render the builder view.
        $this->render(function() {
            // Prepare form data based on validated controller properties.
            $form = [
                "additive"         => count($this->additive) > 0 ? $this->additive : $this->calculator->getAdditive(),
                "ratio"            => $this->ratio,
                "density"          => $this->density, // Density is relevant here
                "elements"         => $this->elements,
                "element_units"    => $this->element_units,
                "show_suggestions" => true, // Suggestions are typically shown in builder
                // Include expert mode fields
                "additive_elements"    => $this->additive_elements,
                "fertilizer_elements"  => $this->fertilizer_elements,
                "target_weeks"         => $this->target_weeks,
                "target_calcium"       => $this->target_calcium,
                "target_magnesium"     => $this->target_magnesium,
            ];
            // Return data for the view.
            return [
                "form"               => $form,
                "result"             => $this->validated ? $this->calculator->calculate() : null, // Calculate result if validated
                "available_elements" => $this->available_elements, // Use expert elements list
                "calculator"         => $this->calculator,
            ];
        }, "builder"); // Specify the 'builder.phtml' view.
    }

    /**
     * Handle API requests.
     * Validates the incoming JSON payload, performs the calculation using the Calculator,
     * and returns the result (or an error) as a JSON response.
     *
     * @param array $payload The decoded JSON input payload.
     * @return void
     */
    public function api(array $payload): void {
        $message = null; // Initialize error message
        try {
            // Validate the payload.
            $this->validate($payload);
        } catch (Exception $e) {
            // If validation fails, mark as invalid and store the error message.
            $this->validated = false;
            $message = $e->getMessage();
        }

        // Set the response content type to JSON.
        // Use try-catch as headers might already be sent in some edge cases.
        try {
            header('Content-Type: application/json');
        } catch (Exception $e) {
            // Log or handle the "headers already sent" error if necessary.
        }

        // If validation failed, return a JSON error response with a 400 status code.
        if (!$this->validated) {
            echo json_encode([
                "error" => $message ?? "Invalid input", // Use specific message or default
            ]);
            // Set HTTP response code to 400 Bad Request.
            try {
                http_response_code(400);
            } catch (Exception $e) {
                // Handle potential errors setting response code.
            }
            return; // Stop execution.
        }

        // If validation succeeded, return a JSON success response with the calculation result.
        echo json_encode([
            "version" => Application::VERSION, // Include app version
            "result"  => $this->calculator->calculate(), // Get the calculation result
        ]);
    }

    /**
     * Render a view template.
     * Includes the header, the specified view file, and the footer.
     * Extracts data returned by the callback function into the view's scope.
     *
     * @param callable $callback A function that returns an array of data to be extracted for the view.
     * @param string $view The name of the view file (without .phtml extension) in `resources/views/`. Defaults to 'calculator'.
     * @return void
     */
    private function render(callable $callback, string $view = "calculator"): void {
        // Include the common header template.
        include __DIR__ . '/../resources/views/header.phtml';

        // Execute the callback to get view data and extract it into the current scope.
        // Variables like $form, $result, $calculator become available in the included view file.
        extract($callback());

        // Include the main content view template.
        include __DIR__ . '/../resources/views/' . $view . '.phtml';

        // Include the common footer template.
        include __DIR__ . '/../resources/views/footer.phtml';
    }

    /**
     * Validate the incoming request payload and update controller properties.
     * This method handles validation for all modes (standard, expert, compare, builder, API).
     * It checks data types, ranges, and existence of selected fertilizers/additives.
     * It also configures the Calculator instance based on the validated data.
     *
     * @param array $payload The request payload.
     * @throws Exception If validation fails.
     * @return void
     */
    private function validate(array $payload): void {
        // Extract data from payload, providing defaults from current controller state.
        $fertilizer = $payload['fertilizer'] ?? "";
        $additive = $payload['additive'] ?? [];
        $region = $payload['region'] ?? $this->region;
        $ratio = $payload['ratio'] ?? $this->ratio;
        $density = $payload['density'] ?? $this->density;
        $volume = $payload['volume'] ?? $this->volume;
        $support_dilution = $payload['support_dilution'] ?? $this->support_dilution;
        $target_offset = $payload['target_offset'] ?? $this->target_offset;
        $elements = $payload['elements'] ?? $this->elements; // Water elements
        $element_units = $payload['element_units'] ?? $this->element_units; // Water element units
        $additive_concentration = $payload['additive_concentration'] ?? []; // Additive concentrations (ml/L)
        $additive_units = $payload['additive_units'] ?? []; // Additive units (mg or ml)
        // Expert mode fields
        $additive_elements = $payload['additive_elements'] ?? []; // Custom additive composition
        $fertilizer_elements = $payload['fertilizer_elements'] ?? []; // Custom fertilizer composition
        $target_weeks = $payload['target_weeks'] ?? []; // Weeks per grow stage
        $target_calcium = $payload['target_calcium'] ?? []; // Target Ca per stage
        $target_magnesium = $payload['target_magnesium'] ?? []; // Target Mg per stage

        // Re-check expert mode status based on GET parameter for this specific request.
        $is_expert_mode = ($_GET["expert"] ?? null);
        if ($is_expert_mode) {
            $this->available_elements = Config::get("app.expert_elements");
        }

        // --- Basic Type and Existence Checks ---
        if (!is_string($fertilizer) || !is_array($additive) || !is_array($elements) || !is_array($additive_concentration) || !is_array($additive_units)) {
            throw new Exception("Invalid input: Basic types mismatch.");
        }
        // Check if selected fertilizer exists (unless it's empty, meaning custom/none).
        if ($fertilizer !== "" && !isset($this->calculator->getFertilizers()[$fertilizer])) {
            throw new Exception("Invalid fertilizer selected.");
        }
        // Check if selected additives exist.
        $_additives = $this->calculator->getAdditives();
        foreach ($additive as $elm => $value) {
            // Allow empty value (meaning custom/none)
            if ($value !== "" && (!isset($_additives[$elm]) || !isset($_additives[$elm][$value]))) {
                throw new Exception("Invalid additive selected for " . $elm);
            }
        }

        // --- Value Range Checks ---
        if ($ratio <= 0) {
            throw new Exception("Invalid ratio: Must be positive.");
        }
        if ($density <= 0) { // Relevant for builder mode
            throw new Exception("Invalid density: Must be positive.");
        }
        if ($volume <= 0) {
            throw new Exception("Invalid volume: Must be positive.");
        }
        // Target offset is a percentage.
        if ($target_offset < -100.0 || $target_offset > 100.0) {
            throw new Exception("Invalid target offset: Must be between -100 and 100.");
        }
        // Check if selected region is valid.
        if (!isset($this->regions[$region])) {
            throw new Exception("Invalid region selected.");
        }

        // --- Element and Unit Validation ---
        // Define all potentially valid elements (from calculator defaults + config + extras).
        $_valid_elements_list = [
            ...$this->calculator->getElements(), // Elements known by calculator
            ...array_keys($this->elements),     // Initial water elements
            "sulphate", "nitrate", "nitrite", "chloride", // Additional possible elements
        ];
        // Validate submitted water elements.
        foreach ($elements as $element => $value) {
            if (!in_array($element, $_valid_elements_list)) {
                throw new Exception("Invalid element provided in water composition: " . $element);
            }
            $elements[$element] = (float)$value; // Ensure numeric value
        }
        // Validate submitted additive concentrations.
        foreach ($additive_concentration as $element => $concentration) {
            if (!in_array($element, $_valid_elements_list)) {
                throw new Exception("Invalid element provided for additive concentration: " . $element);
            }
            $additive_concentration[$element] = (float)$concentration; // Ensure numeric
        }
        // Validate and sanitize submitted additive units.
        foreach ($additive_units as $element => $unit) {
            if (!in_array($element, $_valid_elements_list)) {
                throw new Exception("Invalid element provided for additive unit: " . $element);
            }
            // Allow only 'ml' or 'mg', default to 'mg'.
            $additive_units[$element] = match (strtolower($unit)) {
                "ml", "mg" => $unit,
                default => "mg",
            };
            // If unit is 'mg', concentration is effectively 100% (or irrelevant). Set to 100 for consistency.
            if ($additive_units[$element] === "mg") {
                $additive_concentration[$element] = 100.0;
            }
        }

        // --- Boolean Conversion ---
        // Convert support_dilution from string/form value to boolean.
        if (!is_bool($support_dilution)) {
            $support_dilution = match (strtolower((string)$support_dilution)) {
                "true", "on", "yes", "1" => true,
                default => false,
            };
        }

        // --- Update Calculator Settings (Common) ---
        $this->calculator->setDilutionSupport($support_dilution);

        // --- Update Controller Properties (Common) ---
        $this->fertilizer = $fertilizer;
        $this->additive = $additive; // Store selected additive names
        $this->ratio = (float)$ratio;
        $this->density = (float)$density;
        $this->volume = (float)$volume;
        $this->support_dilution = $support_dilution;
        $this->target_offset = (float)$target_offset;
        $this->region = $region;
        // Store potentially updated concentrations/units based on validation.
        $this->additive_concentration = $additive_concentration;
        $this->additive_units = $additive_units;

        // Convert submitted water elements to mg/L based on their units and update controller property.
        $this->elements = $this->convertElements(array_merge($this->elements, $elements), array_merge($this->element_units, $element_units));

        // --- Expert Mode Specific Validation and Setup ---
        if ($is_expert_mode) {

            // Check consistency of target arrays (must have same keys/count and be non-empty).
            if (count($target_calcium) !== count($target_weeks) || count($target_calcium) !== count($target_magnesium) || count($target_calcium) === 0) {
                throw new Exception("Invalid input: Target weeks, calcium, and magnesium arrays must match and be non-empty.");
            }

            // Validate structure of custom additive elements.
            if (!is_array($additive_elements["calcium"] ?? null) || !is_array($additive_elements["magnesium"] ?? null)) {
                throw new Exception("Invalid input: Custom additive elements structure is incorrect (missing calcium or magnesium).");
            }
            // Deeper structure check (expecting element keys like 'CaO', 'MgO').
            // Note: This check seems overly specific and might need adjustment based on actual expected structure.
            // It currently checks for the presence of 'calcium' within 'calcium' and 'magnesium' within 'magnesium'.
            if (!is_array($additive_elements["calcium"]["calcium"] ?? null) || !is_array($additive_elements["magnesium"]["magnesium"] ?? null)) {
                 // Allowing this structure for now, might need refinement.
                 // throw new Exception("Invalid input: Deeper custom additive elements structure is incorrect.");
            }

            // Validate numeric values within custom additive elements.
            foreach ($additive_elements as $element => $_additive_elements) { // e.g., $element = 'calcium'
                foreach ($_additive_elements as $additive_key => $value) { // e.g., $additive_key = 'custom_calcium' (or predefined name)
                    if (!is_array($value)) {
                        throw new Exception("Invalid input: Custom additive element value must be an array.");
                    }
                    // Example check: Ensure at least CaO or MgO is present and numeric. Adjust as needed.
                    // if ((!isset($value["CaO"]) || !is_numeric($value["CaO"])) && (!isset($value["MgO"]) || !is_numeric($value["MgO"]))) {
                    //     throw new Exception("Invalid input: Custom additive element definition requires numeric CaO or MgO.");
                    // }
                    // Ensure all defined sub-elements are numeric floats.
                    foreach ($value as $key => $val) {
                        $value[$key] = (float)$val;
                    }
                    // Ensure the sum is not zero (meaningless additive).
                    if (array_sum($value) === 0) {
                        throw new Exception("Invalid input: Custom additive element definition sums to zero.");
                    }
                    $additive_elements[$element][$additive_key] = $value; // Update with converted floats
                }
            }

            // Validate target values per grow state.
            foreach (GrowState::getStates() as $state) {
                $state_value = $state->value; // e.g., 'veg', 'flower'
                if (!isset($target_calcium[$state_value]) || !isset($target_magnesium[$state_value]) || !isset($target_weeks[$state_value])) {
                    throw new Exception("Invalid input: Missing target data for grow state: " . $state_value);
                }
                // Convert targets to floats.
                $target_calcium[$state_value] = (float)$target_calcium[$state_value];
                $target_magnesium[$state_value] = (float)$target_magnesium[$state_value];
                $target_weeks[$state_value] = (float)$target_weeks[$state_value];
                // Basic sanity checks.
                if ($target_weeks[$state_value] <= 0) {
                    $target_weeks[$state_value] = 1; // Default to 1 week if invalid
                }
                if ($target_calcium[$state_value] <= 0 && $target_magnesium[$state_value] <= 0) {
                    // Allow zero targets, but maybe log a warning?
                    // throw new Exception("Invalid input: Target calcium and magnesium cannot both be zero or less for state: " . $state_value);
                }
                // Ensure targets are not negative.
                if ($target_calcium[$state_value] < 0) $target_calcium[$state_value] = 0;
                if ($target_magnesium[$state_value] < 0) $target_magnesium[$state_value] = 0;
            }

            // --- Prepare Custom Fertilizer/Additives for Calculator ---

            // Ensure fertilizer element arrays exist.
            if (!isset($fertilizer_elements["calcium"])) $fertilizer_elements["calcium"] = [];
            if (!isset($fertilizer_elements["magnesium"])) $fertilizer_elements["magnesium"] = [];

            // Determine the name for the custom fertilizer. Use a translated default if elements are provided.
            if (array_sum($fertilizer_elements["calcium"]) + array_sum($fertilizer_elements["magnesium"]) === 0.0) {
                $fertilizer_name = ""; // No custom fertilizer defined
            } else {
                $fertilizer_name = __("content.form.fertilizer.custom.label"); // Use translated "Custom" label
            }

            // Define the custom fertilizer structure for the calculator.
            $custom_fertilizer = [
                "name"     => $fertilizer_name,
                "elements" => $fertilizer_elements,
                "density"  => $this->density, // Use validated density
            ];

            // Define the custom additives structure for the calculator.
            // Use translated labels for names.
            $custom_additives = [
                "calcium"   => [
                    "name"     => __("content.form.additive.calcium.label"),
                    "elements" => $additive_elements["calcium"] ?? [], // Use validated custom elements
                    "density"  => 1, // Assuming density 1 for custom additives unless specified otherwise
                ],
                "magnesium" => [
                    "name"     => __("content.form.additive.magnesium.label"),
                    "elements" => $additive_elements["magnesium"] ?? [], // Use validated custom elements
                    "density"  => 1,
                ],
            ];

            // Prepare the target structure for the calculator.
            $targets = [];
            foreach ($target_weeks as $index => $week) { // $index is grow state value ('veg', 'flower')
                $targets[$index] = [
                    "weeks"    => $week,
                    "elements" => [
                        "calcium"   => $target_calcium[$index] ?? 0,
                        "magnesium" => $target_magnesium[$index] ?? 0,
                    ],
                ];
            }

            // Mark as validated *before* updating calculator state.
            $this->validated = true;

            // Update controller properties specific to expert mode.
            $this->additive_elements = $additive_elements;
            $this->fertilizer_elements = $fertilizer_elements;
            $this->target_weeks = $target_weeks;
            $this->target_calcium = $target_calcium;
            $this->target_magnesium = $target_magnesium;

            // --- Configure Calculator with Expert Mode Data ---
            // Add the custom fertilizer if it was defined.
            if ($fertilizer_name !== "") {
                $this->calculator->addFertilizer($fertilizer_name, $custom_fertilizer);
            }
            // Set the (potentially custom) fertilizer.
            $this->calculator->setFertilizer($fertilizer_name);

            // Add and set the custom additives. Use fixed keys 'custom_calcium', 'custom_magnesium'.
            $this->calculator->addAdditive("calcium", "custom_calcium", $custom_additives["calcium"]);
            $this->calculator->addAdditive("magnesium", "custom_magnesium", $custom_additives["magnesium"]);
            // Select these custom additives. Concentrations are handled internally for custom ones.
            $this->calculator->setAdditive(["calcium" => "custom_calcium", "magnesium" => "custom_magnesium"], $this->additive_concentration);

            // Set ratio, targets, target offset, and water composition.
            $this->calculator->setRatio($this->ratio, 1.0); // Assuming base ratio 1 for custom? Check Calculator logic.
            $this->calculator->setTargets($targets);
            $this->calculator->setTargetOffset($this->target_offset / 100); // Convert percentage to fraction

            try {
                $this->calculator->setWater(["elements" => $this->elements]);
            } catch (Exception $e) {
                // Handle potential errors from setWater, though unlikely with prior validation.
                throw new Exception("Error setting water composition: " . $e->getMessage());
            }

        } else {
            // --- Standard Mode Setup ---
            try {
                // Update controller properties (redundant but ensures consistency).
                $this->additive_elements = $additive_elements;
                $this->fertilizer_elements = $fertilizer_elements;
                $this->target_weeks = $target_weeks;
                $this->target_calcium = $target_calcium;
                $this->target_magnesium = $target_magnesium;

                // Set the selected predefined fertilizer and additives.
                $this->calculator->setFertilizer($this->fertilizer);
                $this->calculator->setAdditive($this->additive, $this->additive_concentration); // Pass concentrations for potential liquid additives

                // Re-apply standard targets (might be necessary if calculator state was modified).
                // This part seems potentially problematic - it overwrites targets with only calcium.
                // It should likely use the default targets loaded by the calculator initially.
                // Consider refactoring how targets are handled between modes.
                $targets = [];
                foreach ($this->calculator->getTargets() as $index => $target) {
                    $targets[$index] = [
                        "weeks"    => $target["weeks"],
                        "elements" => [
                            "calcium"   => $target["elements"]["calcium"],
                            // Magnesium seems missing here in standard mode target setting?
                        ],
                    ];
                }
                $this->calculator->setRatio($this->ratio, 1.0); // Base ratio 1?
                $this->calculator->setTargets($targets); // Apply potentially incomplete targets
                $this->calculator->setWater(["elements" => $this->elements]);
                $this->calculator->setTargetOffset($this->target_offset / 100); // Convert percentage

            } catch (Exception $e) {
                // Handle exceptions during standard setup.
                throw new Exception("Error configuring calculator in standard mode: " . $e->getMessage());
            }
            // Mark as validated if no exceptions occurred.
            $this->validated = true;
        }
    }

    /**
     * Convert element values from various units (like mmol/L) to the standard mg/L used internally.
     * Uses molar masses for conversion where applicable.
     *
     * @param array $elements Associative array of elements and their values (e.g., ["calcium" => 10]).
     * @param array $element_units Associative array of elements and their units (e.g., ["calcium" => "mmol"]).
     * @return array The elements array with all values converted to mg/L.
     */
    private function convertElements(array $elements, array $element_units): array {
        // Iterate through each element provided.
        foreach ($elements as $element => $value) {
            // Get the unit for the current element, default to 'mg' if not specified.
            $unit = $element_units[$element] ?? "mg";

            // Perform conversion based on the unit.
            $elements[$element] = match (strtolower($unit)) { // Convert unit to lowercase for case-insensitivity
                // If unit is mmol/L, convert to mg/L using molar mass.
                "mmol" => match ($element) {
                    "calcium"    => $value * 40.08,  // Ca
                    "magnesium"  => $value * 24.31,  // Mg
                    "potassium"  => $value * 39.10,  // K
                    "iron"       => $value * 55.85,  // Fe
                    "sulphate"   => $value * 96.06,  // SO4
                    "nitrate"    => $value * 62.00,  // NO3
                    "nitrite"    => $value * 46.01,  // NO2
                    "phosphorus" => $value * 30.97,  // P
                    "nitrogen"   => $value * 14.01,  // N
                    "sulfur"     => $value * 32.06,  // S (alias for sulphate?)
                    "sodium"     => $value * 22.99,  // Na
                    "chloride"   => $value * 35.45,  // Cl
                    "manganese"  => $value * 54.94,  // Mn
                    "boron"      => $value * 10.81,  // B
                    "zinc"       => $value * 65.38,  // Zn
                    "copper"     => $value * 63.55,  // Cu
                    "molybdenum" => $value * 95.94,  // Mo
                    default      => $value, // If element not recognized, return original value (should not happen with validation)
                },
                // If unit is 'mg' or anything else, assume it's already mg/L (or ppm).
                default => $value,
            };
        }
        // Return the array with converted values.
        return $elements;
    }

    /**
     * Handle language switching request.
     * Updates the session locale based on the input.
     *
     * @param array|null $input Decoded JSON payload containing the 'locale'.
     * @return void
     */
    public function switchLanguage(?array $input): void {
        // Log the attempt to switch language
        error_log('Attempting to switch language with input: ' . print_r($input, true));
        
        // Ensure session is active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            error_log('Session started in switchLanguage');
        } else {
            error_log('Session already active in switchLanguage');
        }

        $locale = $input['locale'] ?? null;
        error_log('Locale from input: ' . $locale);

        // Basic validation: check if locale is provided and is a string
        if ($locale && is_string($locale)) {
            // Further validation: check if the locale is supported (e.g., 'en', 'de')
            // Assuming Translator or Config class can provide supported locales
            $supportedLocales = Config::get('app.locales', ['en', 'de']); // Default if not in config
            if (in_array($locale, $supportedLocales)) {
                $_SESSION['locale'] = $locale;
                // Optionally, update the Translator instance immediately
                Translator::getInstance()->setLocale($locale);
                error_log('Session locale set to: ' . $locale);
                
                // Send success response
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'locale' => $locale, 'session_id' => session_id()]);
                exit;
            } else {
                error_log('Unsupported locale provided: ' . $locale);
                // Send error response for unsupported locale
                header('Content-Type: application/json');
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'error' => 'Unsupported locale', 'session_id' => session_id()]);
                exit;
            }
        } else {
            error_log('Invalid or missing locale in input.');
            // Send error response for invalid input
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'error' => 'Invalid input: locale missing or not a string', 'session_id' => session_id()]);
            exit;
        }
    }
}

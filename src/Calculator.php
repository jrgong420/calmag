<?php
/*
* File: Calculator.php
* Category: -
* Author: M.Goldenbaum
* Created: 05.11.24 18:19
* Updated: -
*
* Description:
*  -
*/

namespace Webklex\CalMag;


use Webklex\CalMag\Enums\GrowState;

/**
 * Class Calculator
 *
 * Core class responsible for calculating the required amounts of fertilizer and additives
 * to reach target nutrient levels (primarily Calcium and Magnesium) in a given volume of water,
 * considering the initial water composition and a desired Ca:Mg ratio.
 * It handles different grow stages, fertilizers, additives, and potential water dilution.
 */
class Calculator {

    /** @var array Target nutrient levels (ppm or mg/L) for different grow states (e.g., veg, flower). */
    protected array $targets = [];

    /** @var array Available additives loaded from config, structured by element (calcium, magnesium). */
    protected array $additives = [];

    /** @var array The desired ratio of Calcium to Magnesium. */
    protected array $ratios = [
        "calcium"   => 3.5, // Default Ca ratio part
        "magnesium" => 1.0, // Default Mg ratio part (usually 1)
    ];

    /** @var array The initial composition of the water source (elements in mg/L). */
    protected array $water = [
        "calcium"   => 0.001, // Default minimal Ca
        "magnesium" => 0.001, // Default minimal Mg
    ];

    /** @var array Available fertilizers loaded from config. */
    protected array $fertilizers = [];

    /** @var string The name/key of the currently selected base fertilizer. */
    protected string $fertilizer = "";
    /** @var array The names/keys of the currently selected additives [element => additive_name]. */
    protected array $additive = [
        "calcium"   => "",
        "magnesium" => "",
    ];

    /** @var bool Whether to consider diluting the source water if its initial nutrient levels exceed the target. */
    protected bool $dilution_support = true;

    /**
     * Calculator constructor.
     * Initializes the calculator with water composition, selected fertilizer/additives, and target ratio.
     * Loads configuration for fertilizers, additives, and default targets.
     * Performs initial setup (boot process).
     *
     * @param array $water Associative array containing initial water 'elements' (mg/L). Requires 'calcium' and 'magnesium'.
     * @param string $fertilizer The name of the initially selected fertilizer.
     * @param array $additive Associative array of initially selected additives [element => name].
     * @param float $ratio The desired Calcium part of the Ca:Mg ratio (Mg part is assumed 1).
     * @throws \InvalidArgumentException If water composition doesn't include calcium and magnesium.
     */
    public function __construct(array $water, string $fertilizer = "", array $additive = [], float $ratio = 3.5) {
        // Basic validation for initial water composition.
        if (!isset($water["elements"]["calcium"]) || !isset($water["elements"]["magnesium"])) {
            throw new \InvalidArgumentException("Water needs to have calcium and magnesium values");
        }

        // Load available additives from configuration.
        $this->additives = Config::get("additives", []);

        // Load fertilizers from configuration, structuring them by a combined 'Brand - Product' name.
        foreach (Config::get("fertilizers", []) as $brand_key => $brand) {
            foreach ($brand["products"] as $product_key => $product) {
                // Skip products that don't contain Ca or Mg (as they are irrelevant for this calculator).
                if (!isset($product["elements"]["calcium"]) && !isset($product["elements"]["magnesium"])) {
                    continue;
                }
                // Store the product details, adding the brand name for clarity.
                $this->fertilizers[$brand["brand_name"] . " - " . $product["name"]] = [
                    ...$product, // Spread product attributes
                    "brand" => $brand["brand_name"],
                ];
            }
        }

        // Set initial state based on constructor arguments.
        $this->setRatio($ratio, 1.0); // Set the target Ca:Mg ratio.
        $this->setFertilizer($fertilizer); // Set the selected fertilizer.
        $this->setAdditive($additive); // Set the selected additives.

        // Perform initial calculations and validations on loaded data.
        $this->boot();

        // Set the initial water composition.
        $this->setWater($water);
    }

    /**
     * Bootstrapping process.
     * Calculates initial properties for loaded fertilizers and additives:
     * - Calculates the inherent Ca:Mg ratio for each fertilizer.
     * - Adjusts fertilizer element values based on density.
     * - Calculates the 'real' concentration (mg/L added per ml/L) for each additive based on its declared concentration and density.
     * - Validates default targets loaded from config.
     *
     * @return void
     */
    private function boot(): void {
        // Process each loaded fertilizer.
        foreach ($this->fertilizers as $index => $fertilizer) {
            // Summarize base elements (Ca, Mg) to calculate the fertilizer's ratio.
            $elements = $this->summarizeElements($fertilizer["elements"]);
            // Avoid division by zero if Mg is missing or zero.
            $this->fertilizers[$index]["ratio"] = ($elements['magnesium'] > 0) ? ($elements['calcium'] / $elements['magnesium']) : INF;
            // Adjust element amounts based on density (if provided, default 1.0).
            $density = $fertilizer["density"] ?? 1.0;
            foreach ($this->fertilizers[$index]["elements"] as $component => $value) {
                if (is_array($value)) { // Handle nested elements (e.g., CaO, MgO)
                    foreach ($value as $sub_element => $sub_value) {
                        $this->fertilizers[$index]["elements"][$component][$sub_element] = $sub_value * $density;
                    }
                } else {
                    $this->fertilizers[$index]["elements"][$component] = $value * $density;
                }
            }
        }

        // Process each loaded additive.
        foreach ($this->additives as $element => $additives) { // $element is 'calcium' or 'magnesium'
            foreach ($additives as $index => $additive) { // $index is additive name
                // Calculate and store the 'real' nutrient contribution per ml/L.
                $this->additives[$element][$index] = $this->calculateRealAdditiveConcentrations($additive);
            }
        }

        // Load and validate default targets from application config.
        foreach (Config::get("app.targets", []) as $index => $target) { // $index is grow state name
            $this->targets[$index] = $this->validateTarget($target);
        }
    }

    /**
     * Calculates the actual amount (mg/L) of each element added per ml/L of the additive.
     * Takes into account the additive's declared concentration (%), density, and element composition (which might include oxides like CaO, MgO).
     * Stores the result in the 'real' key of the additive array.
     *
     * @param array $additive The additive configuration array.
     * @return array The additive array updated with 'real' concentrations.
     */
    protected function calculateRealAdditiveConcentrations(array $additive): array {
        $additive['real'] = []; // Initialize 'real' concentration array
        if (!isset($additive["elements"])) {
            $additive["elements"] = []; // Ensure elements array exists
        }
        // Summarize base elements (Ca, Mg, etc.) from the additive's composition (handling oxides).
        $summarized_elements = $this->summarizeElements($additive['elements']);
        foreach ($summarized_elements as $component => $value) {
            // Formula: (Element_mg_per_100g * 10) * (Concentration_% / 100) * Density
            // This gives mg of element per ml of additive, then assumes 1ml additive per L water? Needs verification.
            // The * 10 seems to convert % (g/100g) to mg/ml assuming density 1? Let's trace:
            // value (mg element / 100g additive) * 10 => (mg element / 10g additive) ???
            // concentration / 100 => fraction
            // density => g/ml
            // Let's assume 'value' is % w/w of the element (e.g., 20% Ca means 20g Ca per 100g additive)
            // Value (g Ca / 100g add) * 1000 (mg/g) => mg Ca / 100g add
            // Concentration (% v/v or w/v?) - Assuming % w/v (g solute / 100ml solvent) for liquids? Config isn't clear.
            // Let's assume concentration is % w/w of the *active ingredient form* (e.g., 20% CaO).
            // And 'value' is the % of the *element* (Ca) in that form (e.g., 71.43% Ca in CaO).
            // Let's re-evaluate the formula based on standard chemistry:
            // Target: mg Element per L water, when adding 1 ml Additive per L water.
            // Additive: Concentration C (% w/w, e.g., 20g active / 100g total), Density D (g/ml)
            // Element % in active form: P_elem (e.g., 0.7143 g Ca / g CaO)
            // 1 ml additive = D grams additive.
            // Grams active in 1 ml = D * (C / 100)
            // Grams element in 1 ml = D * (C / 100) * P_elem
            // mg element in 1 ml = D * (C / 100) * P_elem * 1000
            // If adding 1 ml additive to 1 L water, concentration is mg/L.
            // mg/L = D * C * P_elem * 10
            // The original formula: `(($value * 10) * ($additive['concentration'] / 100)) * ($additive["density"] ?? 1.0)`
            // Here, $value seems to be P_elem * 100 (e.g., 71.43 for CaO).
            // So, `value / 100` is P_elem.
            // Formula becomes: `(((P_elem * 100) * 10) * (C / 100)) * D`
            // `= (P_elem * 1000 * C / 100) * D`
            // `= (P_elem * 10 * C) * D`
            // This matches the derived formula. So $value in summarizeElements must be % element (e.g., 71.43).
            $density = $additive["density"] ?? 1.0;
            $concentration_fraction = ($additive['concentration'] ?? 100) / 100; // Default 100% if not specified
            // $value is mg element per 100g (or per 100ml if density=1) from summarizeElements
            $mg_per_ml_additive = ($value * 10) * $concentration_fraction * $density; // This seems to be mg element per ml additive
            $additive['real'][$component] = $mg_per_ml_additive; // Store mg element per ml additive

            // Ensure the original elements array also has this component key (for safety).
            if (!isset($additive["elements"][$component])) {
                $additive["elements"][$component] = 0;
            }
        }
        return $additive; // Return updated additive array
    }

    /**
     * Validates a target configuration array for a specific grow state.
     * Ensures 'calcium' and 'magnesium' elements exist. If one is missing or zero,
     * it calculates it based on the other element and the desired Ca:Mg ratio.
     * Ensures 'weeks' is an integer.
     *
     * @param array $target The target configuration array (containing 'elements' and 'weeks').
     * @return array The validated and potentially updated target array.
     */
    protected function validateTarget(array $target): array {
        // Default elements if not provided in the target. Use minimal values.
        $elements = $target['elements'] ?? [
            "calcium"   => 0.001,
            "magnesium" => 0.001,
        ];

        // Ensure both Ca and Mg targets are positive. If one is missing/zero, calculate from the other using the ratio.
        if (!isset($elements['calcium']) || $elements['calcium'] <= 0) {
            // Calculate Ca from Mg using the set ratio.
            $elements['calcium'] = ($elements['magnesium'] ?? 0.001) * $this->ratios['calcium'];
        } elseif (!isset($elements['magnesium']) || $elements['magnesium'] <= 0) {
            // Calculate Mg from Ca using the set ratio.
            $elements['magnesium'] = ($elements['calcium'] ?? 0.001) / $this->ratios['calcium'];
        }
        $target['elements'] = $elements; // Update target with validated/calculated elements.
        $target['weeks'] = intval($target['weeks'] ?? 1); // Ensure weeks is an integer, default 1.
        return $target;
    }

    /**
     * Main calculation method.
     * Calculates the initial water deficiency ratio, the required fertilizer/additives for each target state,
     * and generates a detailed week-by-week result table.
     *
     * @return array An array containing 'deficiency', 'results' (per state), and 'table' (week-by-week).
     */
    public function calculate(): array {
        $deficiency = $this->getDeficiencyRatio(); // Calculate initial water Ca:Mg ratio state.
        $results = $this->getAppliedFertilizer(); // Calculate required amounts for each defined target state.
        $table = $this->generateResultTable(); // Generate the detailed weekly table.
        return [
            "deficiency" => $deficiency, // Initial water ratio info
            "results"    => $results,    // Per-state calculation results
            "table"      => $table,      // Weekly breakdown
        ];
    }

    /**
     * Generates a detailed week-by-week table showing target levels, calculated additions,
     * resulting element levels, water usage, and suggested additive adjustments.
     * Interpolates target levels linearly within each grow state based on its duration.
     *
     * @return array The structured result table.
     */
    public function generateResultTable(): array {
        // Get the starting elements from the first defined grow state (e.g., Propagation) or use a default.
        $start_elements = $this->targets[GrowState::Propagation->value]["elements"] ?? [
            "calcium" => 40, // Default starting Ca if first state is missing
        ];
        // Ensure Mg is present based on ratio if only Ca is defaulted.
        if (!isset($start_elements["magnesium"])) {
             $start_elements["magnesium"] = $start_elements["calcium"] / $this->ratios["calcium"];
        }

        $weeks_data = []; // Array to hold data for each week
        $week_num = 0; // Overall week counter

        // Iterate through each defined grow state target.
        foreach ($this->targets as $state => $target) {
            $end_elements = $target["elements"]; // Target elements at the *end* of this state.
            $delta_elements = []; // Change in elements over the duration of this state.

            // Calculate the difference between end and start elements for this state.
            foreach ($end_elements as $component => $value) {
                $delta_elements[$component] = $value - ($start_elements[$component] ?? 0);
            }

            // Interpolate target elements for each week within this state.
            $num_weeks_in_state = max(1, $target["weeks"]); // Ensure at least 1 week
            for ($i = 0; $i < $num_weeks_in_state; $i++) {
                $current_week_target_elements = [];
                // Linear interpolation: start + (delta / total_weeks) * (current_week_in_state + 1)
                foreach ($end_elements as $component => $value) {
                    $current_week_target_elements[$component] = ($start_elements[$component] ?? 0) + ($delta_elements[$component] / $num_weeks_in_state) * ($i + 1);
                }

                // Ensure Mg target respects the ratio based on the interpolated Ca target for this week.
                if (isset($current_week_target_elements["calcium"])) {
                    $current_week_target_elements["magnesium"] = $current_week_target_elements["calcium"] / $this->ratios["calcium"];
                }

                // Calculate fertilizer/additive amounts needed to reach this week's interpolated target.
                $_result_for_week = $this->calculateFertilizer([
                    ...$target, // Pass other target info (like offset)
                    "elements" => $current_week_target_elements, // Use interpolated elements for this week
                ]);

                // Store the results for this week.
                $week_num++; // Increment overall week number
                $weeks_data[$week_num] = [
                    "result"          => $_result_for_week, // Full calculation result for the week
                    "week"            => $week_num,
                    "state"           => $state, // Grow state name
                    "target_elements" => $current_week_target_elements, // Interpolated target for the week
                ];
            }

            // The end elements of this state become the start elements for the next state.
            $start_elements = $end_elements;
        }

        // Get details of the selected additives for easier access.
        $ca_additive = $this->additives["calcium"][$this->additive["calcium"] ?? ""] ?? [];
        $mg_additive = $this->additives["magnesium"][$this->additive["magnesium"] ?? ""] ?? [];

        // Initialize the final table structure.
        $table = [
            "targets"    => $this->targets, // Original state targets
            "fertilizer"  => [
                "name" => $this->fertilizer, // Selected fertilizer name
                "rows" => [], // Weekly amounts (ml/L)
            ],
            "ca_additive" => [
                "name" => $this->additive["calcium"] ?? "", // Selected Ca additive name
                "concentration" => $ca_additive["concentration"] ?? 0, // Its concentration
                "rows" => [], // Weekly amounts (ml/L and mg/L)
            ],
            "mg_additive" => [
                "name" => $this->additive["magnesium"] ?? "", // Selected Mg additive name
                "concentration" => $mg_additive["concentration"] ?? 0, // Its concentration
                "rows" => [], // Weekly amounts (ml/L and mg/L)
            ],
            "elements"    => [], // Resulting element levels each week (mg/L)
            "water"       => [], // Water usage (dilution factor) each week
            "ratio"       => [], // Resulting Ca:Mg ratio each week
            "target"      => [], // Target elements for each week (interpolated)
            "missing"     => [], // Initial deficit compared to target before additions
            "suggested"   => [], // Suggested alternative additive concentrations/amounts
        ];

        // Populate the table rows using the calculated weekly data.
        foreach ($weeks_data as $week_num => $week_data) {
            $result = $week_data["result"];
            $table["fertilizer"]["rows"][$week_num] = $result["fertilizer"]["ml"] ?? 0;
            $table["ca_additive"]["rows"][$week_num] = $result["additive"]["calcium"] ?? []; // Contains ml, mg, name, concentration
            $table["mg_additive"]["rows"][$week_num] = $result["additive"]["magnesium"] ?? []; // Contains ml, mg, name, concentration
            $table["elements"][$week_num] = $result["elements"]; // Final element levels after additions
            $table["water"][$week_num] = [
                "water"    => $result["water"], // Fraction of pure water (1 - dilution)
                "dilution" => $result["dilution"], // Fraction of source water used
            ];
            $table["ratio"][$week_num] = $result["ratio"]; // Final Ca:Mg ratio
            $table["target"][$week_num] = [
                ...$result["target"], // Original target info (weeks, offset)
                "state" => $week_data["state"], // Grow state for this week
                "elements" => $week_data["target_elements"], // Interpolated target elements
            ];
            $table["missing"][$week_num] = $result["missing"]; // Initial deficit
            $table["suggested"][$week_num] = $result["suggested_additive"]; // Suggestions
        }

        return $table; // Return the populated table
    }

    /**
     * Calculates the required fertilizer/additives for each defined target state *without* weekly interpolation.
     * Useful for getting a summary result for each grow state's endpoint.
     *
     * @return array Associative array [state => calculation_result]
     */
    public function getAppliedFertilizer(): array {
        $result = [];
        // Calculate for each defined target state.
        foreach ($this->targets as $state => $target) {
            $result[$state] = $this->calculateFertilizer($target);
        }
        return $result;
    }

    /**
     * Summarizes element contributions from a complex element array.
     * Handles nested structures (like CaO, MgO) and converts them to base elements (Ca, Mg).
     * Ensures 'calcium' and 'magnesium' keys exist in the result.
     *
     * @param array $elements An array where keys are element names (or compounds like CaO) and values are amounts (e.g., percentages).
     * @return array An array with summarized base element amounts (e.g., ["calcium" => 71.43, "magnesium" => 60.32]).
     */
    public function summarizeElements(array $elements): array {
        // Initialize result with base elements.
        $result = [
            "calcium"   => 0,
            "magnesium" => 0,
        ];
        // Iterate through the input element structure.
        foreach ($elements as $component => $value) {
            // Ensure the component key exists in the result array.
            if (!isset($result[$component])) {
                $result[$component] = 0;
            }
            // If the value is an array, it represents compound contributions (e.g., ["CaO" => 100]).
            if (is_array($value)) {
                foreach ($value as $sub_element => $sub_value) {
                    // Convert compound to base element using stoichiometric ratios.
                    $result[$component] += match ($sub_element) {
                        "CaO" => $sub_value * 0.7143, // %Ca in CaO
                        "MgO" => $sub_value * 0.6032, // %Mg in MgO
                        // Add other conversions if needed (e.g., P2O5 to P, K2O to K)
                        default => $sub_value, // Assume it's already the base element if not recognized
                    };
                }
            } else {
                // If not an array, assume it's the base element amount directly.
                $result[$component] += $value;
            }
        }
        return $result; // Return summarized base element amounts.
    }

    /**
     * Core calculation logic to determine fertilizer and additive amounts for a *single* target.
     * 1. Calculates necessary dilution based on initial water and target.
     * 2. Applies base fertilizer iteratively until Ca or Mg target is approached.
     * 3. Applies Ca/Mg additives iteratively to fine-tune the ratio and reach final targets.
     * 4. Includes a refinement step if targets are missed and dilution is possible.
     *
     * @param array $target The target configuration for a specific point in time (e.g., end of a state or a specific week). Contains 'elements' array.
     * @return array Detailed calculation results for this target, including amounts, final elements, ratio, dilution, etc.
     */
    public function calculateFertilizer(array $target): array {
        $result = []; // Initialize result array

        // Get the configuration for the selected fertilizer (or empty if none selected).
        $fertilizer = $this->fertilizers[$this->fertilizer] ?? [
            "elements" => [], // Default to no elements if fertilizer not found or selected
        ];
        // Start with the initial water composition (summarized base elements).
        $elements = $this->summarizeElements($this->water["elements"]);
        $initial_water_elements = $elements; // Keep a copy for later 'missing' calculation
        $dilution = 1.0; // Assume full strength source water initially (no dilution)

        // --- Step 1: Calculate Initial Dilution (if needed and supported) ---
        if ($this->dilution_support) {
            foreach ($target["elements"] as $component => $target_value) {
                // Ensure component exists in current elements, default to 0.
                if (!isset($elements[$component])) {
                    $elements[$component] = 0;
                }
                // If initial water element level exceeds target, calculate required dilution factor.
                if ($elements[$component] > $target_value && $target_value > 0) { // Avoid division by zero target
                    // Dilution factor needed for this specific element.
                    $required_dilution_for_component = $target_value / $elements[$component];
                    // Apply the *most stringent* dilution required across all elements.
                    $dilution = min($dilution, $required_dilution_for_component);
                }
            }
            // Apply the calculated dilution to all elements in the starting water.
            if ($dilution < 1.0) {
                foreach ($elements as $element => $element_value) {
                    $elements[$element] = $element_value * $dilution;
                }
            }
        }

        // --- Step 2: Apply Base Fertilizer ---
        $fertilizer_nanoliter = 0; // Counter for fertilizer amount (in 0.01 ml steps)
        // Summarize the elements provided by the selected fertilizer.
        $fertilizer_elements = $this->summarizeElements($fertilizer["elements"]);

        // Apply fertilizer only if it actually contains Ca and Mg.
        if (($fertilizer_elements['calcium'] ?? 0) > 0 && ($fertilizer_elements['magnesium'] ?? 0) > 0) {
            // Iteratively add small amounts (0.01 ml) of fertilizer.
            // Continue as long as *both* Ca and Mg are below their respective targets.
            // This assumes the fertilizer helps approach both targets simultaneously.
            // Note: This loop might overshoot one target while trying to reach the other if the fertilizer ratio doesn't match the target ratio well.
            $max_fertilizer_iterations = 50000; // Safety break
            while (
                ($elements['calcium'] < $target["elements"]['calcium']) &&
                ($elements['magnesium'] < $target["elements"]['magnesium']) &&
                $max_fertilizer_iterations-- > 0 // Safety break
            ) {
                // Add the contribution of 0.01 ml fertilizer to each element.
                // ($value * 10) / 100 => mg element per ml fertilizer / 100 => mg element per 0.01 ml fertilizer
                foreach ($fertilizer_elements as $component => $value) {
                    if (!isset($elements[$component])) $elements[$component] = 0;
                    // Add mg contributed by 0.01 ml fertilizer to the current water concentration (mg/L).
                    $elements[$component] += ($value * 10) / 100;
                }
                $fertilizer_nanoliter++; // Increment amount added
            }
             if ($max_fertilizer_iterations <= 0) error_log("Warning: Max fertilizer iterations reached.");
        }

        // Store the calculated fertilizer amount.
        $result['fertilizer'] = [
            "ml"   => $fertilizer_nanoliter / 100, // Convert 0.01ml steps to ml
            "name" => $this->fertilizer,
        ];

        // Ensure minimal Ca/Mg values to avoid division by zero later.
        if (!isset($elements["calcium"]) || $elements["calcium"] <= 0) $elements["calcium"] = 0.001;
        if (!isset($elements["magnesium"]) || $elements["magnesium"] <= 0) $elements["magnesium"] = 0.001;

        // --- Step 3: Apply Additives to Adjust Ratio and Reach Targets ---
        $result['additive'] = []; // Initialize additive results array
        foreach ($this->additive as $element => $name) { // $element = 'calcium' or 'magnesium', $name = selected additive name
            // Get the config for the selected additive.
            $additive_config = $this->additives[$element][$name] ?? null;

            // If no additive is selected or found for this element, record zero addition.
            if ($additive_config === null) {
                $result['additive'][$element] = [ "ml" => 0, "mg" => 0, "name" => $name, "concentration" => 100 ];
                continue;
            }

            $additive_nanoliter = 0; // Counter for additive amount (0.01 ml steps)
            $max_additive_iterations = 50000; // Safety break

            // Get the 'real' element contributions per ml for this additive.
            $additive_real_elements = $additive_config['real'];

            // Loop condition: Continue adding additive if:
            // 1. Ratio is wrong: Current Ca/Mg > Target Ratio (add Mg-rich additive) OR Current Ca/Mg < Target Ratio (add Ca-rich additive)
            // 2. OR Targets not met: Both Ca and Mg are still below target levels.
            // This combined condition allows adding additive primarily to fix the ratio, but also to top up levels if needed.

            // Add Mg-rich additive if ratio is too high OR both targets unmet
            while (
                ($elements['calcium'] / $elements['magnesium'] > $this->ratios['calcium']) ||
                (
                    ($elements['calcium'] < $target["elements"]['calcium']) &&
                    ($elements['magnesium'] < $target["elements"]['magnesium'])
                ) && $max_additive_iterations-- > 0 // Safety break
            ) {
                // Only proceed if this is the Magnesium additive and it actually adds more Mg than Ca.
                if ($element !== 'magnesium' || !isset($additive_real_elements["magnesium"]) || $additive_real_elements["magnesium"] <= ($additive_real_elements["calcium"] ?? 0)) {
                    break; // Stop adding this additive if it's not the right one or ineffective
                }
                // Add contribution of 0.01 ml additive.
                foreach ($additive_real_elements as $component => $value) {
                    if (!isset($elements[$component])) $elements[$component] = 0;
                    $elements[$component] += $value / 100; // mg element per 0.01 ml additive
                }
                $additive_nanoliter++;
            }

            // Add Ca-rich additive if ratio is too low OR both targets unmet
            while (
                 ($elements['calcium'] / $elements['magnesium'] < $this->ratios['calcium']) ||
                 (
                    ($elements['calcium'] < $target["elements"]['calcium']) &&
                    ($elements['magnesium'] < $target["elements"]['magnesium'])
                 ) && $max_additive_iterations-- > 0 // Safety break
            ) {
                 // Only proceed if this is the Calcium additive and it actually adds more Ca than Mg.
                if ($element !== 'calcium' || !isset($additive_real_elements["calcium"]) || $additive_real_elements["calcium"] <= ($additive_real_elements["magnesium"] ?? 0)) {
                    break; // Stop adding this additive if it's not the right one or ineffective
                }
                 // Add contribution of 0.01 ml additive.
                foreach ($additive_real_elements as $component => $value) {
                    if (!isset($elements[$component])) $elements[$component] = 0;
                    $elements[$component] += $value / 100; // mg element per 0.01 ml additive
                }
                $additive_nanoliter++;
            }
             if ($max_additive_iterations <= 0) error_log("Warning: Max additive iterations reached for $element.");


            // --- Step 3b: Refine Additive Amount (Small Adjustment) ---
            // This section seems intended to remove the last 0.1ml if it caused overshoot,
            // but the logic is a bit complex and might not always be correct.
            // It checks if ratio is wrong after adding > 0.1ml and removes 0.01ml if Ca ratio is low.
            // Then it removes all additive added if the amount was < 0.1ml.
            // This might need review for robustness.
            if ($additive_nanoliter > 10) { // If more than 0.1ml was added
                // If ratio is now too low (needs more Ca relative to Mg)
                if ($elements['calcium'] / $elements['magnesium'] < $this->ratios['calcium']) {
                    // Backtrack by removing the last 0.01ml increment's contribution.
                    foreach ($additive_real_elements as $component => $value) {
                        if ($value > 0) $elements[$component] -= $value / 100;
                    }
                    $additive_nanoliter -= 1;
                }
            } elseif ($additive_nanoliter > 0) { // If 0.01ml to 0.1ml was added
                 // Remove all added additive - effectively deciding not to use small amounts?
                do {
                    foreach ($additive_real_elements as $component => $value) {
                         if ($value > 0) $elements[$component] -= $value / 100;
                    }
                    $additive_nanoliter -= 1;
                } while ($additive_nanoliter > 0);
            }

            // Calculate the mass (mg) of the additive used, based on volume (ml) and concentration (%).
            // Assumes concentration is % w/v (g/100ml) or similar interpretation needed.
            // Formula: ml * (concentration / 100) gives grams? Then * 1000 for mg.
            $additive_ml = $additive_nanoliter / 100;
            $concentration_fraction = ($additive_config['concentration'] ?? 100) / 100;
            // Assuming concentration is % w/w and density is needed, or % w/v.
            // If % w/v (e.g., 20g/100ml), then grams = ml * (C/100).
            // If % w/w (e.g., 20g/100g), then grams = ml * density * (C/100).
            // The original formula seems to imply % w/v:
            $additive_grams = $additive_ml * $concentration_fraction;

            // Store the calculated additive amount (ml and mg).
            $result['additive'][$element] = [
                "ml"            => $additive_ml,
                "mg"            => $additive_grams * 1000, // Convert grams to mg
                "name"          => $name,
                "concentration" => $additive_config['concentration'],
            ];
        }

        // Store the target configuration used for this calculation.
        $result["target"] = $target;

        // Calculate the initial 'missing' amounts before any additions (target - initial water).
        $result['missing'] = [
            "calcium"   => max(0, $target["elements"]['calcium'] - ($initial_water_elements['calcium'] ?? 0)),
            "magnesium" => max(0, $target["elements"]['magnesium'] - ($initial_water_elements['magnesium'] ?? 0)),
        ];

        // Calculate suggested alternative additive amounts/concentrations based on the 'missing' values.
        $result['suggested_additive'] = $this->getSuggestedAdditives($result['missing']);

        // Store the final calculated ratio, element levels, and water dilution factors.
        $result["ratio"] = ($elements['magnesium'] > 0) ? ($elements['calcium'] / $elements['magnesium']) : INF;
        $result["elements"] = $elements; // Final element levels after all additions
        $result["dilution"] = $dilution; // Final dilution factor applied to source water
        $result["water"] = 1.0 - $dilution; // Fraction of pure/RO water needed

        // --- Step 4: Refinement if Target Not Reached (Optional Dilution Adjustment) ---
        // Check if targets are met within a tolerance (e.g., 5%).
        $tolerance = 0.05;
        $ca_target_reached = abs($result["elements"]["calcium"] - $result["target"]["elements"]["calcium"]) <= ($result["target"]["elements"]["calcium"] * $tolerance);
        $mg_target_reached = abs($result["elements"]["magnesium"] - $result["target"]["elements"]["magnesium"]) <= ($result["target"]["elements"]["magnesium"] * $tolerance);
        $target_reached = $ca_target_reached && $mg_target_reached;

        // If target not reached, dilution is supported, some dilution happened initially (>10%), and a fertilizer was used...
        // This attempts a different approach: find optimal dilution first, then apply fertilizer.
        // This logic seems complex and potentially overlaps/conflicts with the iterative approach above. Needs careful review.
        if (!$target_reached && $dilution > 0.1 && $this->fertilizer !== "" && $this->dilution_support) {
            $initial_water_elements_copy = $this->summarizeElements($this->water["elements"]); // Start again with initial water

            // Calculate ratios of water and fertilizer.
            $ca_water_ratio = ($initial_water_elements_copy['magnesium'] > 0) ? ($initial_water_elements_copy['calcium'] / $initial_water_elements_copy['magnesium']) : INF;
            $ca_fertilizer_ratio = ($fertilizer_elements['magnesium'] > 0) ? ($fertilizer_elements['calcium'] / $fertilizer_elements['magnesium']) : INF;

            // Only attempt this if water ratio > fertilizer ratio (meaning fertilizer helps lower the ratio).
            // This condition seems specific and might not cover all refinement scenarios.
            if ($ca_water_ratio > $ca_fertilizer_ratio) {
                // Iteratively add fertilizer to a *copy* of initial water until the ratio is within tolerance of the target ratio.
                // This finds how much fertilizer is needed just to correct the ratio.
                $temp_elements = $initial_water_elements_copy;
                $runs = 5000; // Safety break
                do {
                    $_ratio = ($temp_elements['magnesium'] > 0) ? ($temp_elements['calcium'] / $temp_elements['magnesium']) : INF;
                    // Check if ratio is outside tolerance band.
                    if ($_ratio > $this->ratios['calcium'] * (1 + $tolerance) || $_ratio < $this->ratios['calcium'] * (1 - $tolerance)) {
                        // Add 0.01ml fertilizer contribution.
                        foreach ($fertilizer_elements as $component => $value) {
                            if (!isset($temp_elements[$component])) $temp_elements[$component] = 0;
                            $temp_elements[$component] += ($value * 10) / 100;
                        }
                    } else {
                        break; // Ratio is within tolerance
                    }
                } while ($runs-- > 0);
                 if ($runs <= 0) error_log("Warning: Max ratio correction iterations reached.");


                // Now, calculate the dilution factor needed to scale these ratio-corrected elements down to match the *target* levels.
                $ca_factor = ($temp_elements['calcium'] > 0) ? ($target["elements"]['calcium'] / $temp_elements['calcium']) : 1.0;
                $mg_factor = ($temp_elements['magnesium'] > 0) ? ($target["elements"]['magnesium'] / $temp_elements['magnesium']) : 1.0;

                // The required dilution is the minimum of these factors.
                $new_dilution = min($ca_factor, $mg_factor);

                // If this new dilution is valid (<= 1.0), apply it and recalculate fertilizer needed.
                if ($new_dilution <= 1.0 && $new_dilution > 0) { // Ensure positive dilution
                    // Update the main result's dilution factors.
                    $result["dilution"] = $new_dilution;
                    $result["water"] = 1.0 - $new_dilution;

                    // Recalculate elements starting from the newly diluted water.
                    $elements = $this->summarizeElements($this->water["elements"]);
                    foreach ($elements as $element => $element_value) {
                        $elements[$element] = $element_value * $new_dilution;
                    }

                    // Recalculate fertilizer needed to reach target from this new starting point.
                    $fertilizer_nanoliter = 0;
                    $max_fertilizer_iterations_2 = 50000; // Safety break
                    if (($fertilizer_elements['calcium'] ?? 0) > 0 && ($fertilizer_elements['magnesium'] ?? 0) > 0) {
                        while (
                            ($elements['calcium'] < $target["elements"]['calcium']) &&
                            ($elements['magnesium'] < $target["elements"]['magnesium']) &&
                             $max_fertilizer_iterations_2-- > 0 // Safety break
                        ) {
                            foreach ($fertilizer_elements as $component => $value) {
                                if (!isset($elements[$component])) $elements[$component] = 0;
                                $elements[$component] += ($value * 10) / 100; // mg/ml
                            }
                            $fertilizer_nanoliter++;
                        }
                         if ($max_fertilizer_iterations_2 <= 0) error_log("Warning: Max fertilizer iterations reached (Refinement).");
                    }
                    // Update final ratio, elements, and fertilizer amount in the result.
                    // Note: This refinement doesn't re-apply additives, which might be necessary.
                    $result["ratio"] = ($elements['magnesium'] > 0) ? ($elements['calcium'] / $elements['magnesium']) : INF;
                    $result["elements"] = $elements;
                    $result['fertilizer'] = [
                        "ml"   => $fertilizer_nanoliter / 100,
                        "name" => $this->fertilizer,
                    ];
                    // Additives remain as calculated in Step 3, which might now be incorrect after dilution change.
                }
            }
        }

        return $result; // Return the final calculated result for the target.
    }

    /**
     * Calculates suggested additive amounts or concentrations needed to cover the initial water deficiency.
     * This provides hints to the user if their selected additives aren't ideal for the starting water.
     *
     * @param array $missing Associative array [element => missing_amount_mg_L].
     * @return array Associative array [element => suggestion_details].
     */
    public function getSuggestedAdditives(array $missing): array {
        $suggested_additive = [];
        // Iterate through Ca and Mg additives.
        foreach ($this->additives as $element => $additives) { // $element = 'calcium' or 'magnesium'
            $_missing = $missing[$element] ?? 0; // Get the calculated missing amount for this element.
            if ($_missing <= 0) continue; // Skip if no deficit for this element.

            // Get the config for the *currently selected* additive for this element.
            $_additive = $this->additives[$element][$this->additive[$element] ?? ""] ?? null;
            if ($_additive === null) continue; // Skip if no additive selected for this element.

            // Calculate the 'real' contribution of this additive at 100% concentration (pure solid).
            // This represents the maximum potential of the additive's base composition.
            $_elements_at_100_conc = $this->calculateRealAdditiveConcentrations([
                "elements"      => $_additive['elements'],
                "concentration" => 100, // Assume 100% concentration for base calculation
                "density"       => $_additive['density'] ?? 1.0,
            ])["real"];

            // Check if the additive actually provides the element needed.
            if (($_elements_at_100_conc[$element] ?? 0) <= 0) continue;

            // Calculate the concentration (%) needed if 1 ml/L of the additive were to provide the exact missing amount.
            // Formula derivation:
            // Missing (mg/L) = Real_Contribution_at_C% (mg/L per ml/L) * 1 (ml/L)
            // Missing = (Real_Contribution_at_100% * (C / 100)) * 1
            // C = (Missing / Real_Contribution_at_100%) * 100
            $_delta = $_missing / $_elements_at_100_conc[$element]; // Factor needed relative to 100% concentration contribution
            $_concentration = $_delta * 100; // Required concentration (%) if using 1 ml/L
            $_ml = 1.0; // Assume 1 ml/L initially

            // If the required concentration exceeds 100%, it means more than 1 ml/L is needed at 100% concentration.
            if ($_concentration > 100) {
                $_ml = $_concentration / 100; // Calculate the volume (ml/L) needed at 100% concentration.
                $_concentration = 100; // Cap concentration at 100%.
            }

            // Store the suggestion details.
            $suggested_additive[$element] = [
                "missing"  => $_missing, // The initial deficit
                "additive" => $this->additive[$element], // The currently selected additive
                "ml"       => $_ml, // Suggested ml/L (if conc > 100, else 1.0)
                // Include the full additive details calculated at the suggested concentration.
                ...$this->calculateRealAdditiveConcentrations([
                    "elements"      => $_additive['elements'],
                    "concentration" => $_concentration, // Suggested concentration
                    "density"       => $_additive['density'] ?? 1.0,
                ])
            ];
        }
        return $suggested_additive;
    }

    /**
     * Calculates the initial Ca:Mg ratio of the source water.
     * Returns the ratio normalized so one component is 1.0.
     *
     * @return array ['calcium' => ratio_part, 'magnesium' => ratio_part]
     */
    public function getDeficiencyRatio(): array {
        $ca = $this->water["elements"]['calcium'] ?? 0.001;
        $mg = $this->water["elements"]['magnesium'] ?? 0.001;

        // Avoid division by zero if either is missing/zero.
        if ($ca <= 0 || $mg <= 0) {
            return ["calcium" => 1.0, "magnesium" => 1.0]; // Return 1:1 if data is invalid
        }

        // Normalize the ratio.
        if ($ca > $mg) {
            return [
                "calcium"   => $ca / $mg,
                "magnesium" => 1.0,
            ];
        } else { // Includes the case where ca == mg
            return [
                "calcium"   => 1.0,
                "magnesium" => $mg / $ca,
            ];
        }
    }

    /**
     * Sets the currently selected fertilizer.
     *
     * @param string $fertilizer The name/key of the fertilizer.
     * @return void
     * @throws \InvalidArgumentException If the fertilizer name is not found (and not empty).
     */
    public function setFertilizer(string $fertilizer): void {
        // Allow empty string (no fertilizer selected).
        if ($fertilizer !== "" && !isset($this->fertilizers[$fertilizer])) {
            throw new \InvalidArgumentException("Fertilizer '$fertilizer' not found");
        }
        $this->fertilizer = $fertilizer;
    }

    /**
     * Sets the currently selected additives and updates their concentrations if provided.
     *
     * @param array $additives Associative array [element => additive_name].
     * @param array $concentrations Optional associative array [element => concentration_percent].
     * @return void
     * @throws \InvalidArgumentException If an additive name is not found (and not empty).
     */
    public function setAdditive(array $additives, array $concentrations = []): void {
        // Validate selected additive names.
        foreach ($additives as $element => $additive) {
            // Allow empty string (no additive selected for this element).
            if ($additive !== "" && (!isset($this->additives[$element]) || !isset($this->additives[$element][$additive]))) {
                throw new \InvalidArgumentException("Additive '$additive' for element '$element' not found");
            }
        }
        // Store the selected additive names.
        $this->additive = $additives;

        // Update concentrations for the selected additives if provided.
        foreach ($concentrations as $element => $concentration) {
            $selected_additive_name = $additives[$element] ?? null;
            // Only update if an additive is actually selected for this element and concentration is provided.
            if ($selected_additive_name && isset($this->additives[$element][$selected_additive_name])) {
                // Update the concentration in the main additives array.
                $this->additives[$element][$selected_additive_name]['concentration'] = (float)$concentration;
                // Recalculate the 'real' contributions based on the new concentration.
                $this->additives[$element][$selected_additive_name] = $this->calculateRealAdditiveConcentrations($this->additives[$element][$selected_additive_name]);
            }
        }
    }

    /**
     * Sets the initial water composition.
     * Performs some basic conversions (e.g., sulphate to sulfur, nitrate/nitrite to nitrogen).
     * Ensures minimal Ca and Mg values exist.
     * Filters the water elements to only include those relevant to the selected fertilizer/additives.
     *
     * @param array $water Associative array containing 'elements'.
     * @return void
     */
    public function setWater(array $water): void {
        // Get the elements provided by the currently selected fertilizer.
        $fertilizer_config = $this->fertilizers[$this->fertilizer] ?? ["elements" => []];
        $fertilizer_elements_provided = array_keys($this->summarizeElements($fertilizer_config["elements"]));

        // --- Basic Element Conversions (e.g., from common water report formats) ---
        // Note: These conversions modify the input $water array directly.
        if (isset($water["elements"]["sulphate"])) {
            $water["elements"]["sulfur"] = ($water["elements"]["sulfur"] ?? 0) + ($water["elements"]["sulphate"] * 0.333); // S from SO4
        }
        // This 'elements' variable seems undefined here, likely a typo, should be $water["elements"]?
        // if (isset($elements["chloride"])) {
        //     $water["elements"]["chlorine"] = ($water["elements"]["chlorine"] ?? 0) + $elements["chloride"] * 0.5256; // Cl from Cl? (Seems redundant)
        // }
        if (isset($water["elements"]["chloride"])) { // Corrected potential typo
             $water["elements"]["chlorine"] = ($water["elements"]["chlorine"] ?? 0) + $water["elements"]["chloride"]; // Assuming chloride is Cl
        }
        if (isset($water["elements"]["nitrate"])) {
            $water["elements"]["nitrogen"] = ($water["elements"]["nitrogen"] ?? 0) + ($water["elements"]["nitrate"] * 0.2259); // N from NO3
        }
        if (isset($water["elements"]["nitrite"])) {
            $water["elements"]["nitrogen"] = ($water["elements"]["nitrogen"] ?? 0) + ($water["elements"]["nitrite"] * 0.3043); // N from NO2
        }
        // Ensure minimal positive values for Ca and Mg to avoid calculation errors.
        if (!isset($water["elements"]["calcium"]) || $water["elements"]["calcium"] <= 0) {
            $water["elements"]["calcium"] = 0.001;
        }
        if (!isset($water["elements"]["magnesium"]) || $water["elements"]["magnesium"] <= 0) {
            $water["elements"]["magnesium"] = 0.001;
        }

        // Store the processed water data, but initialize 'elements' to empty first.
        $this->water = [
            ...$water, // Keep other potential water properties (like name, source)
            "elements" => [], // Start with an empty elements list for the internal state
        ];

        // --- Filter Water Elements ---
        // Create a list of all elements potentially relevant based on fertilizer and selected additives.
        $relevant_elements = $fertilizer_elements_provided;
        foreach ($this->additive as $element => $name) {
            if ($name === "") continue; // Skip if no additive selected
            $additive_config = $this->additives[$element][$name] ?? null;
            if ($additive_config) {
                $relevant_elements = array_merge($relevant_elements, array_keys($this->summarizeElements($additive_config["elements"])));
            }
        }
        // Always include Ca and Mg.
        $relevant_elements = array_unique(array_merge($relevant_elements, ['calcium', 'magnesium']));

        // Populate the internal water state with only the relevant elements from the input water.
        foreach ($relevant_elements as $element_key) {
             $this->water["elements"][$element_key] = $water["elements"][$element_key] ?? 0.0; // Default to 0 if not present in input
        }
    }

    /**
     * Applies a percentage offset to the target element levels for all grow states.
     *
     * @param float $offset The offset percentage (e.g., 0.1 for +10%, -0.05 for -5%).
     * @return void
     */
    public function setTargetOffset(float $offset): void {
        // Iterate through each defined target state.
        foreach ($this->targets as $index => $target) {
            // Apply the offset to Ca and Mg targets.
            $this->targets[$index]['elements']['calcium'] *= (1 + $offset);
            $this->targets[$index]['elements']['magnesium'] *= (1 + $offset);
            // Re-validate the target after applying the offset to ensure consistency.
            // This might adjust one element if the other became zero/negative due to a large negative offset.
            $this->targets[$index] = $this->validateTarget($this->targets[$index]);
        }
    }

    /**
     * Sets the desired Calcium to Magnesium ratio.
     *
     * @param float $calcium The Calcium part of the ratio.
     * @param float $magnesium The Magnesium part of the ratio (usually 1.0).
     * @return void
     */
    public function setRatio(float $calcium, float $magnesium): void {
        // Ensure magnesium part is positive to avoid division by zero.
        if ($magnesium <= 0) $magnesium = 1.0;
        // Store the ratio components.
        $this->ratios = [
            "calcium"   => $calcium,
            "magnesium" => $magnesium,
        ];
        // Re-validate all targets as the desired ratio has changed.
        // This will adjust the Mg target based on the Ca target for each state according to the new ratio.
        foreach ($this->targets as $index => $target) {
            $this->targets[$index] = $this->validateTarget($target);
        }
    }

    /**
     * Gets the currently stored water composition.
     *
     * @return array The water data array including 'elements'.
     */
    public function getWater(): array {
        return $this->water;
    }

    /**
     * Calculates the amount (mg/L) of each element contributed by a given volume (ml/L) of the currently selected fertilizer.
     *
     * @param float $ml The volume of fertilizer in ml (per Liter of water).
     * @return array Associative array [element => amount_mg_L].
     */
    public function getFertilizerComponents(float $ml): array {
        // Get the config of the selected fertilizer.
        $fertilizer = $this->fertilizers[$this->fertilizer] ?? ["elements" => []];
        $result = [];

        // Summarize the base elements provided by the fertilizer.
        $elements = $this->summarizeElements($fertilizer["elements"]);

        // Calculate contribution: (mg element / 100g) * 10 => mg/ml? * ml => total mg?
        // Assuming ($value * 10) is mg element per ml fertilizer.
        foreach ($elements as $component => $value) {
            // mg/L = (mg/ml fertilizer) * (ml fertilizer / L water)
            $result[$component] = ($value * 10) * $ml;
        }

        return $result;
    }

    /**
     * Calculates the amount (mg/L) of each element contributed by a given volume (ml/L) of the currently selected additive for a specific element (Ca or Mg).
     *
     * @param string $element The element the additive is for ('calcium' or 'magnesium').
     * @param float $ml The volume of additive in ml (per Liter of water).
     * @return array Associative array [element => amount_mg_L].
     */
    public function getAdditiveComponents(string $element, float $ml): array {
        // Get the config for the selected additive for the specified element.
        $additive = $this->additives[$element][$this->additive[$element] ?? ""] ?? null;
        $result = [];
        if ($additive === null) return $result; // Return empty if no additive selected/found.

        // Use the pre-calculated 'real' contributions (mg element per ml additive).
        foreach ($additive['real'] as $component => $mg_per_ml) {
            // mg/L = (mg element / ml additive) * (ml additive / L water)
            $result[$component] = $mg_per_ml * $ml;
        }

        return $result;
    }

    /**
     * Gets the names of the currently selected additives.
     *
     * @return array ['calcium' => name, 'magnesium' => name]
     */
    public function getAdditive(): array {
        return $this->additive;
    }

    /**
     * Gets the name of the currently selected fertilizer.
     *
     * @return string The fertilizer name/key.
     */
    public function getFertilizer(): string {
        return $this->fertilizer;
    }

    /**
     * Sets the target configuration for a specific grow state.
     *
     * @param GrowState $state The enum instance representing the grow state.
     * @param array $target The target configuration array ('elements', 'weeks').
     * @return void
     */
    public function setTarget(GrowState $state, array $target): void {
        // Validate the target before storing it.
        $this->targets[$state->value] = $this->validateTarget($target);
    }

    /**
     * Gets all currently defined target configurations.
     *
     * @return array Associative array [state_name => target_config].
     */
    public function getTargets(): array {
        return $this->targets;
    }

    /**
     * Gets the list of all available fertilizers loaded from config.
     *
     * @return array Associative array [fertilizer_name => config].
     */
    public function getFertilizers(): array {
        return $this->fertilizers;
    }

    /**
     * Gets the list of all available additives loaded from config.
     * Filters out any potential empty ("") keys used internally.
     *
     * @return array Nested array [element => [additive_name => config]].
     */
    public function getAdditives(): array {
        $additives = [];
        foreach ($this->additives as $element => $additive_group) {
            $additives[$element] = [];
            foreach ($additive_group as $index => $additive) {
                // Exclude the internal empty key placeholder if it exists.
                if ($index != "") {
                    $additives[$element][$index] = $additive;
                }
            }
        }
        return $additives;
    }

    /**
     * Gets a unique, sorted list of all element names present in the initial water,
     * available fertilizers, and available additives.
     *
     * @return array A sorted array of element names.
     */
    public function getElements(): array {
        $elements = [];
        // Add elements from water.
        $elements = array_merge($elements, array_keys($this->water["elements"]));
        // Add elements from fertilizers.
        foreach ($this->fertilizers as $fertilizer) {
             $elements = array_merge($elements, array_keys($this->summarizeElements($fertilizer["elements"])));
        }
        // Add elements from additives.
        foreach ($this->additives as $element => $additives) {
            foreach ($additives as $additive) {
                 $elements = array_merge($elements, array_keys($this->summarizeElements($additive["elements"])));
            }
        }
        // Get unique elements and sort them alphabetically.
        $elements = array_unique($elements);
        sort($elements);
        return $elements;
    }

    /**
     * Gets the specified part (Calcium or Magnesium) of the desired Ca:Mg ratio.
     *
     * @param string $element 'calcium' or 'magnesium'.
     * @return float The ratio value for the specified element. Returns 0 if element not found.
     */
    public function getRatio(string $element): float {
        return $this->ratios[$element] ?? 0.0;
    }

    /**
     * Adds or updates a fertilizer definition dynamically (used in expert mode).
     *
     * @param string $name The name/key for the fertilizer.
     * @param array $fertilizer The fertilizer configuration array.
     * @return void
     */
    public function addFertilizer(string $name, array $fertilizer): void {
        // TODO: Should probably re-run relevant parts of boot() for this fertilizer.
        $this->fertilizers[$name] = $fertilizer;
    }

    /**
     * Adds or updates an additive definition dynamically (used in expert mode).
     *
     * @param string $element The element the additive is for ('calcium' or 'magnesium').
     * @param string $name The name/key for the additive.
     * @param array $additive The additive configuration array.
     * @return void
     */
    public function addAdditive(string $element, string $name, array $additive): void {
         // Recalculate real concentrations when adding/updating.
        $this->additives[$element][$name] = $this->calculateRealAdditiveConcentrations($additive);
    }

    /**
     * Sets multiple target configurations at once (used in expert mode).
     *
     * @param array $targets Associative array [state_name => target_config].
     * @return void
     */
    public function setTargets(array $targets): void {
        foreach ($targets as $index => $target) {
            // Validate each target individually.
            $this->targets[$index] = $this->validateTarget($target);
        }
    }

    /**
     * Enables or disables the water dilution support feature.
     *
     * @param bool $dilution_support True to enable, false to disable.
     * @return void
     */
    public function setDilutionSupport(bool $dilution_support): void {
        $this->dilution_support = $dilution_support;
    }
}

<?php
/*
* File: Comparitor.php
* Category: -
* Author: M.Goldenbaum
* Created: 16.11.24 00:17
* Updated: -
*
* Description:
*  -
*/

namespace Webklex\CalMag;

use Webklex\CalMag\Enums\GrowState;

/**
 * Class Comparator
 *
 * Compares the effect of different fertilizers on the initial water composition
 * to reach target nutrient levels across different grow states. It utilizes the
 * Calculator class internally for each fertilizer.
 *
 * @package Webklex\CalMag
 */
class Comparator {

    /**
     * @var array $water_elements The initial composition of the water source (elements in mg/L).
     */
    protected array $water_elements;

    /**
     * @var array $targets Target nutrient levels and parameters for different grow states.
     */
    protected array $targets;

    /**
     * @var array $ratios The desired ratio of Calcium to Magnesium.
     */
    protected array $ratios;

    /**
     * Comparator constructor.
     * Initializes with water composition and target ratio. Loads and validates default targets.
     *
     * @param array $water_elements Associative array of initial water elements (mg/L).
     * @param float $ratio The desired Calcium part of the Ca:Mg ratio (Mg part is assumed 1).
     */
    public function __construct(array $water_elements, float $ratio = 3.5) {
        // Set the desired Ca:Mg ratio.
        $this->ratios = [
            "calcium"   => $ratio,
            "magnesium" => 1.0,
        ];

        // Load and validate default targets from config for each grow state.
        foreach (Config::get("app.targets", []) as $index => $target) {
            $this->targets[$index] = $this->validateTarget(GrowState::fromString($index), [
                ...$target,
                "elements" => [
                    ...$target["elements"] ?? [], // Keep existing elements
                    // Ensure Ca and Mg keys exist, default to 0 if not present in config target
                    "calcium" => $target['elements']['calcium'] ?? 0,
                    "magnesium" => $target['elements']['magnesium'] ?? 0,
                ]
            ]);
        }

        // Set the initial water composition.
        $this->setWaterElements($water_elements);
    }

    /**
     * Performs the comparison calculation.
     * Iterates through all available fertilizers, creates a Calculator instance for each,
     * and calculates the results (fertilizer/additive amounts) for all target states using that fertilizer.
     *
     * @return array An array where keys are fertilizer names and values are the calculation results (per state) from the Calculator.
     */
    public function calculate(): array {
        $result = []; // Initialize results array

        // Create a temporary calculator just to get the list of available fertilizers.
        // No fertilizer/additives selected initially for this temporary instance.
        $temp_calculator = new Calculator(["elements" => $this->water_elements], "", ["calcium" => "", "magnesium" => ""], $this->ratios["calcium"]);
        $fertilizers = $temp_calculator->getFertilizers();

        // Iterate through each available fertilizer.
        foreach($fertilizers as $fertilizer_name => $fertilizer) {
            // Create a new Calculator instance specifically for this fertilizer, using the comparator's water and ratio.
            // No additives are selected by default in the comparator context.
            $calculator = new Calculator(["elements" => $this->water_elements], $fertilizer_name, ["calcium" => "", "magnesium" => ""], $this->ratios["calcium"]);
            // Calculate the results for all target states using this specific fertilizer.
            $result[$fertilizer_name] = $calculator->getAppliedFertilizer();
        }

        return $result; // Return the comparison results for all fertilizers.
    }

    /**
     * Sets the water element composition for the comparator.
     * Performs basic conversions (sulphate to sulfur, nitrate/nitrite to nitrogen).
     *
     * @param array $water_elements Associative array of water elements (mg/L).
     * @return void
     */
    public function setWaterElements(array $water_elements): void {
        // Perform conversions similar to the Calculator's setWater method.
        if (isset($water_elements["sulphate"])) {
            $water_elements["sulfur"] = ($water_elements["sulfur"] ?? 0) + ($water_elements["sulphate"] * 0.333); // S from SO4
        }
        if (isset($water_elements["nitrate"])) {
            $water_elements["nitrogen"] = ($water_elements["nitrogen"] ?? 0) + ($water_elements["nitrate"] * 0.2259); // N from NO3
        }
        if (isset($water_elements["nitrite"])) {
            $water_elements["nitrogen"] = ($water_elements["nitrogen"] ?? 0) + ($water_elements["nitrite"] * 0.3043); // N from NO2
        }
        // Commented out: Unlike Calculator, Comparator doesn't seem to enforce minimal Ca/Mg here.
        /*if(!isset($water_elements["magnesium"])) {
            $water_elements["magnesium"] = 0.0001;
        }
        if(!isset($water_elements["calcium"])) {
            $water_elements["calcium"] = 0.0001;
        }*/

        // Store the processed water elements.
        $this->water_elements = $water_elements;
    }

    /**
     * Sets the desired Calcium to Magnesium ratio for the comparison.
     * Re-validates all targets based on the new ratio.
     *
     * @param float $calcium The Calcium part of the ratio.
     * @param float $magnesium The Magnesium part of the ratio (usually 1.0).
     * @return void
     */
    public function setRatio(float $calcium, float $magnesium): void {
         // Ensure magnesium part is positive.
        if ($magnesium <= 0) $magnesium = 1.0;
        // Store the new ratio.
        $this->ratios = [
            "calcium"   => $calcium,
            "magnesium" => $magnesium,
        ];
        // Re-validate all targets using the new ratio.
        foreach ($this->targets as $index => $target) {
             // Need to pass the GrowState enum instance to validateTarget
            $state_enum = GrowState::tryFrom($index) ?? GrowState::Vegetation; // Default if index isn't a valid state
            $this->targets[$index] = $this->validateTarget($state_enum, $target); // Pass original target data
        }
    }

    /**
     * Validates a target configuration array for a specific grow state within the Comparator context.
     * Ensures 'calcium' and 'magnesium' elements exist based on the ratio.
     * Merges validated elements with default state parameters (days, pH).
     *
     * @param GrowState $state The GrowState enum instance.
     * @param array $target The target configuration array (containing 'elements', 'weeks', etc.).
     * @return array The validated target array merged with default state parameters.
     */
    protected function validateTarget(GrowState $state, array $target): array {
        $elements = $target['elements'] ?? []; // Get elements from target, default empty

        // Ensure Ca/Mg exist and respect the ratio, similar to Calculator::validateTarget.
        // Use $this->ratios specific to the Comparator instance.
        if (!isset($elements['calcium']) || $elements['calcium'] <= 0) {
            $elements['calcium'] = ($elements['magnesium'] ?? 0.001) * $this->ratios['calcium'];
        } elseif (!isset($elements['magnesium']) || $elements['magnesium'] <= 0) {
            $elements['magnesium'] = ($elements['calcium'] ?? 0.001) / $this->ratios['calcium'];
        }
        // Update the target's elements with validated values.
        $target['elements'] = $elements;

        // Merge the validated elements with default parameters (days, pH) specific to the grow state.
        // Note: The original code merges $elements directly, overwriting other keys in $target.
        // It should merge the validated $target['elements'] back into $target, then merge state defaults.
        $state_defaults = match ($state) {
            GrowState::Propagation => [
                "weeks" => $target['weeks'] ?? 1, // Use 'weeks' consistent with Calculator
                "ph"   => $target['ph'] ?? 6.3,
            ],
            GrowState::Vegetation => [
                "weeks" => $target['weeks'] ?? 3,
                "ph"   => $target['ph'] ?? 6.3,
            ],
            GrowState::Flower, GrowState::LateFlower => [ // Assuming LateFlower shares defaults
                "weeks" => $target['weeks'] ?? 4,
                "ph"   => $target['ph'] ?? 6.3,
            ],
            default => [ // Default case if state is unexpected
                "weeks" => $target['weeks'] ?? 1,
                "ph"   => $target['ph'] ?? 6.3,
            ]
        };

        // Return the original target merged with state defaults and validated elements.
        return array_merge($state_defaults, $target);
    }
}

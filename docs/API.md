# Calculator Features and Capabilities

## What the Calculator Can Do

The CalMag Calculator offers several powerful features to help you manage your nutrient solutions. Here's what you can do with it:

### 1. Calculate Nutrient Solutions

You can calculate the perfect nutrient mix by providing:
- How much water you're using (in liters)
- Your target calcium level (in parts per million)
- Your target magnesium level (in parts per million)
- Which fertilizer you're using
- Any additional supplements you want to add

The calculator will tell you:
- The exact amounts of each product to use
- The final concentrations you'll achieve
- Any recommendations for improvement
- Important warnings to watch out for

### 2. Compare Different Solutions

Want to try different combinations? The calculator can compare multiple solutions at once, showing you:
- How each solution differs
- Which one might be more cost-effective
- Which one might work better for your needs
- Recommendations for the best choice

### 3. Get Product Information

The calculator includes detailed information about:
- Available fertilizers and their properties
- Common additives and their recommended usage
- Safety guidelines for each product
- Compatibility information

### 4. Smart Recommendations

The calculator provides intelligent suggestions based on:
- Best practices for nutrient ratios
- Common growing conditions
- Product compatibility
- Cost efficiency

## Understanding the Results

When you get your results, you'll see:

### Basic Information
- Final calcium concentration
- Final magnesium concentration
- Amount of each product needed

### Additional Details
- Cost estimates
- Efficiency ratings
- Safety warnings
- Usage recommendations

### Warning System
The calculator will alert you about:
- Unsafe combinations
- Unusual concentrations
- Potential issues
- Recommended adjustments

## Getting Help

If something doesn't work as expected:
- Check the error messages for guidance
- Review the recommendations
- Consult the user guide
- Contact support if needed

## Technical Notes

The calculator:
- Updates in real-time as you make changes
- Saves your last used settings
- Works on all modern devices
- Supports multiple languages

## Endpoints

### Calculate
`POST /calculate`

Calculates optimal calcium and magnesium concentrations for nutrient solutions.

#### Request Parameters
```json
{
    "water_volume": number,      // Volume of water in liters
    "target_ca": number,         // Target calcium concentration in ppm
    "target_mg": number,         // Target magnesium concentration in ppm
    "fertilizer": string,        // Selected fertilizer type
    "additives": [               // Optional additives
        {
            "name": string,
            "amount": number,
            "unit": string
        }
    ]
}
```

#### Response
```json
{
    "success": boolean,
    "data": {
        "ca_concentration": number,    // Calculated calcium concentration
        "mg_concentration": number,    // Calculated magnesium concentration
        "recommendations": string[],   // Optimization recommendations
        "warnings": string[]          // Any warnings or notes
    },
    "error": string | null
}
```

### Compare
`POST /compare`

Compares different nutrient solutions.

#### Request Parameters
```json
{
    "solutions": [
        {
            "water_volume": number,
            "target_ca": number,
            "target_mg": number,
            "fertilizer": string,
            "additives": []
        }
    ]
}
```

#### Response
```json
{
    "success": boolean,
    "data": {
        "comparisons": [
            {
                "solution_index": number,
                "ca_concentration": number,
                "mg_concentration": number,
                "cost": number,
                "efficiency": number
            }
        ],
        "recommendations": string[]
    },
    "error": string | null
}
```

### Get Fertilizers
`GET /fertilizers`

Retrieves list of available fertilizers.

#### Response
```json
{
    "success": boolean,
    "data": {
        "fertilizers": [
            {
                "id": string,
                "name": string,
                "description": string,
                "ca_percentage": number,
                "mg_percentage": number
            }
        ]
    }
}
```

### Get Additives
`GET /additives`

Retrieves list of available additives.

#### Response
```json
{
    "success": boolean,
    "data": {
        "additives": [
            {
                "id": string,
                "name": string,
                "description": string,
                "ca_percentage": number,
                "mg_percentage": number,
                "recommended_dosage": {
                    "min": number,
                    "max": number,
                    "unit": string
                }
            }
        ]
    }
}
```

## Error Codes

- `400`: Bad Request - Invalid input parameters
- `404`: Not Found - Resource not found
- `500`: Internal Server Error - Server-side error
- `422`: Validation Error - Invalid data format

## Rate Limiting

- 100 requests per minute per IP address
- Rate limit headers included in response:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset` 
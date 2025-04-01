#!/bin/bash

# Define the base directory for tests
TEST_DIR="tests"
PEST_BIN="vendor/bin/pest"
FAILURE_DETECTED=0

# Check if Pest executable exists
if [ ! -f "$PEST_BIN" ]; then
    echo "Error: Pest executable not found at $PEST_BIN"
    echo "Please run 'composer install'."
    exit 1
fi

echo "Starting individual test runs..."
echo "================================="

# Find all files ending with Test.php within the TEST_DIR
find "$TEST_DIR" -type f -name '*Test.php' | while read -r test_file; do
  echo "Running: $test_file"

  # Run Pest for the single test file.
  # Add -d memory_limit=-1 to rule out memory issues for individual runs too.
  # Use --fail-on-warning to catch more potential issues.
  # Capture output to check for errors, but hide successful runs' details.
  output=$(php -d memory_limit=-1 "$PEST_BIN" "$test_file" --fail-on-warning 2>&1)
  exit_code=$?

  if [ $exit_code -ne 0 ]; then
    echo "-----------------------------------------------------"
    echo "🔴 FAILURE DETECTED for $test_file (Exit Code: $exit_code)"
    echo "-----------------------------------------------------"
    echo "Output:"
    echo "$output"
    echo "-----------------------------------------------------"
    FAILURE_DETECTED=1
    # Optional: Uncomment the next line to stop on the first failure
    # exit $exit_code
  else
    # Optionally show passed tests too, commented out by default
    # echo "✅ PASSED: $test_file"
    : # No-op, just indicates success branch
  fi
  # echo "" # Optional newline for spacing
done

echo "================================="
if [ $FAILURE_DETECTED -ne 0 ]; then
  echo "Finished running tests. One or more tests failed when run individually."
  exit 1
else
  echo "Finished running tests. All tests passed when run individually."
  echo "NOTE: This doesn't guarantee the absence of the original 'composer test' error 255, which might occur during global teardown after *all* tests complete."
  exit 0
fi
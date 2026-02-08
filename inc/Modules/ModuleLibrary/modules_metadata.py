import os
import re
import json
from collections import OrderedDict
import ast

def capture_nested_array(content, start_pos):
    """Capture a nested array as a raw string starting from `start_pos`."""
    open_brackets = 0
    end_pos = start_pos

    for i, char in enumerate(content[start_pos:], start=start_pos):
        if char == '[':
            open_brackets += 1
        elif char == ']':
            open_brackets -= 1
            if open_brackets == 0:
                end_pos = i + 1
                break

    return content[start_pos:end_pos]

def parse_settings(settings_str):
    """Parse the settings string into a structured list of dictionaries."""
    try:
        # Step 1: Replace PHP-style `=>` with JSON-compatible `:`
        settings_str = settings_str.replace("=>", ":")

        # Step 2: Standardize boolean values to JSON-compatible lowercase true/false
        settings_str = settings_str.replace("True", "true").replace("False", "false")

        # Step 3: Ensure all keys are quoted for JSON compatibility
        # Match bare keys (unquoted words followed by `:`) and add quotes around them
        settings_str = re.sub(r"(\b\w+\b)\s*:", r'"\1":', settings_str)

        # Step 4: Ensure all string values are quoted correctly for JSON compatibility
        # Convert single quotes around string values to double quotes
        settings_str = re.sub(r"(?<=: )'([^']*)'", r'"\1"', settings_str)

        # Step 5: Convert all remaining single quotes to double quotes
        settings_str = settings_str.replace("'", '`')

        # Step 6: Parse the modified string as JSON
        parsed_settings = json.loads(settings_str)

        # Verify that parsed settings are in list format as expected
        if isinstance(parsed_settings, list):
            return parsed_settings
        else:
            print("Parsed settings are not in a list format.")
            return []

    except json.JSONDecodeError as e:
        print(f"JSONDecodeError in parsing settings: {e}")
        print("Final string for debugging:", settings_str)  # Print the final string for debugging
        return []
    except Exception as e:
        print(f"Unexpected error in parsing settings: {e}")
        return []

def convert_to_json(input_string):
    return input_string
    try:
        # Initialize result list
        result = []

        # Extract each item using regex
        pattern = r"\['key' => '([^']+)', 'type' => '([^']+)', 'default' => '([^']+)', 'description' => '([^']+)'\]"
        matches = re.findall(pattern, input_string)

        # Process each match
        for match in matches:
            key, type_, default, description = match

            # Handle default value
            if default == 'True':
                default = True
            elif default == 'False':
                default = False
            else:
                default = default.strip("'")

            # Create dictionary
            item = {
                'key': key,
                'type': type_,
                'default': default,
                'description': description
            }
            result.append(item)

        # Convert to JSON without escaped quotes
        json_str = json.dumps(result, separators=(',', ':'))
        return json_str

    except Exception as e:
        return f"Error: {str(e)}"

def parse_initialization_value(initialization_str):
    """Parse initialization values with proper handling for `settings` and `dependencies` arrays."""
    initialization_dict = OrderedDict()

    # Capture 'settings' as structured data instead of raw text
    settings_match = re.search(r"'settings'\s*=>\s*(\[[^\]]*\])", initialization_str, re.DOTALL)
    if settings_match:
        start_pos = settings_match.start(1)
        settings_str = capture_nested_array(initialization_str, start_pos).strip()
        initialization_dict["settings"] = convert_to_json(settings_str)  # Parse settings as structured data
    else:
        initialization_dict["settings"] = []

    # Capture 'dependencies' as raw text instead of parsing
    dependencies_match = re.search(r"'dependencies'\s*=>\s*(\[[^\]]*\])", initialization_str, re.DOTALL)
    if dependencies_match:
        start_pos = dependencies_match.start(1)
        dependencies_str = capture_nested_array(initialization_str, start_pos).strip()
        initialization_dict["dependencies"] = dependencies_str  # Keep dependencies as a raw string
    else:
        initialization_dict["dependencies"] = []

    # Use updated regex patterns for multi-line `description` and `explain` fields
    multi_line_fields = ["description", "explain"]
    for key in multi_line_fields:
        match = re.search(rf"'{key}'\s*=>\s*'((?:[^']|(?<=\\)')*)'", initialization_str, re.DOTALL)
        if match:
            # Capture the full multi-line value
            value = match.group(1)
            initialization_dict[key] = value.replace("'", "\'")  # Convert escaped single quotes to regular quotes

    # Parse other top-level key-value pairs (excluding settings, dependencies, description, and explain)
    top_level_items = re.findall(r"'(\w+)'\s*=>\s*(?:'([^']*)'|(\d+|True|False))", initialization_str)
    for key, str_value, num_bool_value in top_level_items:
        if key in ["settings", "dependencies", "description", "explain"]:
            continue  # Skip fields we've already handled
        if num_bool_value:
            value = True if num_bool_value == "True" else False if num_bool_value == "False" else int(num_bool_value)
        else:
            value = str_value
        initialization_dict[key] = value

    return initialization_dict

def extract_initialization_value(file_content):
    # Match the entire `$initialization` array as a single block
    pattern = re.compile(r'\$initialization\s*=\s*\[(.*?)\];', re.DOTALL)
    match = pattern.search(file_content)
    if match:
        initialization_str = match.group(1).strip()
        ret = parse_initialization_value(initialization_str)
        return ret
    return None

def read_php_files_from_directories(base_path):
    file_info_objects = []
    for root, dirs, files in os.walk(base_path):
        for file_name in files:
            if file_name.endswith('.php'):
                file_path = os.path.join(root, file_name)
                with open(file_path, 'r') as file:
                    content = file.read()
                    namespace_match = re.search(r'namespace\s+([^\s;]+);', content)
                    class_match = re.search(r'class\s+(\w+)', content)
                    initialization_value = extract_initialization_value(content)

                    if namespace_match and class_match and initialization_value:
                        namespace = namespace_match.group(1)
                        class_name = class_match.group(1)

                        # Create a new ordered dictionary with the desired key order
                        ordered_initialization_value = OrderedDict([
                            ("class", f"{namespace}\\{class_name}"),
                            ("file", file_path.lstrip('./'))
                        ])

                        # Define the key order with "explain" after "description"
                        key_order = ["title", "description", "explain", "version", "name", "priority", "dependencies", "settings"]
                        for key in key_order:
                            if key in initialization_value:
                                if key == "settings":
                                    # Parse the settings string to JSON and then compact it
                                    settings_str = initialization_value[key]
                                    try:
                                        # Parse the JSON string
                                        settings_data = json.loads(settings_str)
                                        # Compact it back to a string without extra whitespace
                                        ordered_initialization_value[key] = json.dumps(settings_data, separators=(',', ':'))
                                    except json.JSONDecodeError:
                                        ordered_initialization_value[key] = settings_str
                                else:
                                    ordered_initialization_value[key] = initialization_value[key]
                            elif key == "settings":
                                # Ensure 'settings' key is included as parsed data if not in the PHP content
                                ordered_initialization_value[key] = "[]"
                            elif key == "dependencies":
                                # Ensure 'dependencies' key is included as a raw string if not in the PHP content
                                ordered_initialization_value[key] = initialization_value.get("dependencies", "[]")

                        # Append the ordered dictionary to the list
                        file_info_objects.append(ordered_initialization_value)
    return file_info_objects

def save_to_json(file_info_objects, output_file):
    # Convert each object in file_info_objects to JSON format and save
    data = {"modules": file_info_objects}
    with open(output_file, 'w') as json_file:
        json.dump(data, json_file, indent=4)

if __name__ == "__main__":
    #exit("This script is not meant to be run directly.")

    base_path = '.'  # Current directory
    output_file = 'modules.json'  # Output JSON file
    file_info_objects = read_php_files_from_directories(base_path)
    save_to_json(file_info_objects, output_file)
    print(f"Data saved to {output_file}")

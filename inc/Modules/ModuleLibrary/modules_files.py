import json
import os
import re
from string import Template

# Path to the JSON file
json_file_path = 'modules_metadata.json'

# Template for the PHP class
php_class_template = Template('''<?php
declare(strict_types=1);

namespace RankingCoach\\Inc\\Modules\\ModuleLibrary\\$class_namespace;

use RankingCoach\\Inc\\Modules\\ModuleBase\\BaseModule;
use RankingCoach\\Inc\\Modules\\ModuleManager;

/**
 * Class $class_name
 */
class $class_name extends BaseModule {

    /**
     * $class_name constructor.
     * @param ModuleManager $$moduleManager
     */
    public function __construct(ModuleManager $$moduleManager) {
        $$initialization = [
            'title' => '$title',
            'description' => '$description',
            'version' => '$version',
            'name' => '$name',
            'priority' => $priority,
            'dependencies' => $dependencies,
            'settings' => $settings,
            'explain' => '$explain',
        ];
        parent::__construct($$moduleManager, $$initialization);
    }

    /**
     * Registers the hooks for the module.
     */
    protected function registerHooks(): void {
    }
}
''')

def php_format(value):
    """Convert Python dict/list to PHP array format using short syntax."""
    if isinstance(value, dict):
        return "[" + ", ".join("'{}' => {}".format(k, php_format(v)) for k, v in value.items()) + "]"
    elif isinstance(value, list):
        return "[" + ", ".join(php_format(v) for v in value) + "]"
    elif isinstance(value, str):
        # Escape single quotes for PHP string values
        return "'{}'".format(value.replace("'", "\\'"))
    else:
        return str(value)

def read_json(file_path):
    try:
        with open(file_path, 'r') as file:
            data = json.load(file)
            return data
    except FileNotFoundError:
        print(f"The file {file_path} was not found.")
    except json.JSONDecodeError as e:
        print(f"Error decoding JSON from the file {file_path}: {e}")

def create_php_class(file_path, class_metadata):
    # Retrieve namespace and class name
    class_namespace = class_metadata.get('class', 'UnnamedNamespace')
    class_name = class_namespace.split('\\')[-1]  # Extract last part of the namespace

    # Retrieve and escape fields with potential single quotes
    title = class_metadata.get('title', 'Title').replace("'", "\\'")
    description = class_metadata.get('description', 'Description').replace("'", "\\'")
    version = class_metadata.get('version', '1.0.0')
    name = class_metadata.get('name', 'name')
    priority = class_metadata.get('priority', 0)
    dependencies = php_format(class_metadata.get('dependencies', []))
    settings = php_format(class_metadata.get('settings', {}))
    explain = class_metadata.get('example', 'example').replace("'", "\\'")

    # Substitute placeholders using the Template
    php_class_content = php_class_template.substitute(
        class_name=class_name,
        class_namespace=class_name,
        title=title,
        description=description,
        version=version,
        name=name,
        priority=priority,
        dependencies=dependencies,
        settings=settings,
        explain=explain,
    )

    try:
        with open(file_path, 'w') as file:
            file.write(php_class_content)
        print(f"The file '{file_path}' has been created/updated.")
    except OSError as e:
        print(f"Error writing to file '{file_path}': {e}")

def ensure_files(data):
    if 'modules' in data:
        for module in data['modules']:
            file_path = module.get('file')
            if not file_path:
                print(f"Skipping module with missing 'file' path.")
                continue

            class_metadata = module
            directory = os.path.dirname(file_path)
            if not os.path.isdir(directory):
                try:
                    os.makedirs(directory)
                    print(f"The directory '{directory}' has been created.")
                except OSError as e:
                    print(f"Error creating directory '{directory}': {e}")

            if os.path.isfile(file_path):
                with open(file_path, 'r') as file:
                    content = file.read()
                    if not re.search(r'\bclass\b\s+\w+', content):
                        create_php_class(file_path, class_metadata)
                    else:
                        print(f"The file '{file_path}' already contains a PHP class.")
            else:
                create_php_class(file_path, class_metadata)
    else:
        print("No 'modules' key found in JSON data.")

def main():
    data = read_json(json_file_path)
    if data:
        ensure_files(data)

if __name__ == "__main__":
    exit("This script is not meant to be run directly.")

    main()

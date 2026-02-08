<?php
/**
 * HTML Purifier Custom Definitions Config
 *
 * This configuration file defines custom HTML elements (tags) and attributes
 * to be allowed by HTML Purifier. These elements and attributes are typically
 * filtered out by default but can be enabled using the definitions below.
 *
 * Configuration Blueprint:
 *
 * [
 *     'definition_id' => (string) A unique ID for this custom HTML definition. Used for caching.
 *     'definition_rev' => (int) The revision number for this custom definition. Increment when you make changes.
 *     'elements' => [
 *         'element_name' => [
 *             'type' => (string) The type of element, e.g., 'Block', 'Inline', 'Empty'.
 *             'contents' => (string) Defines what content is allowed inside the element, e.g., 'Flow', 'Inline', 'Empty'.
 *             'attr_collections' => (string) Predefined attribute sets, e.g., 'Common', 'None', etc.
 *             'attributes' => (array) Custom attributes allowed for the element, structured as:
 *                 [
 *                     'attribute_name' => (string) The type of data the attribute accepts, e.g., 'Text', 'URI', 'Boolean', 'Enum#value1,value2'.
 *                 ]
 *         ]
 *     ]
 * ]
 *
 * Definitions:
 * - definition_id: A unique identifier for the custom definition, used to track and cache custom rules.
 * - definition_rev: A revision number. This should be incremented when custom definitions are updated.
 * - element_name: The name of the HTML tag to be allowed (e.g., 'link', 'mycustomtag').
 * - type: The type of the element, such as 'Block' (for block-level elements) or 'Inline' (for inline elements).
 * - contents: Specifies what kind of content is allowed inside the element (e.g., 'Flow', 'Inline', 'Empty').
 * - attr_collections: Predefined sets of attributes, such as 'Common' (for id, class, title, etc.), or 'None' (no attributes allowed).
 * - attributes: Custom attributes specific to the element, in the form of 'attribute_name' => 'type'.
 *
 * Examples:
 *
 * [
 *     'definition_id' => 'custom_definition',
 *     'definition_rev' => 1,
 *     'elements' => [
 *         'link' => [
 *             'type' => 'Inline',
 *             'contents' => 'Flow',
 *             'attr_collections' => 'Common',
 *             'attributes' => [
 *                 'href' => 'URI',
 *                 'target' => 'Enum#_blank,_self',
 *             ]
 *         ],
 *         'mycustomtag' => [
 *             'type' => 'Block',
 *             'contents' => 'Flow',
 *             'attr_collections' => 'Common',
 *             'attributes' => [
 *                 'data-custom' => 'Text',
 *             ]
 *         ]
 *     ]
 * ]
 *
 * Usage:
 * This array is returned and passed into the HTML Purifier configuration
 * to allow custom tags and attributes in user input without them being stripped.
 */

return [
    // HTML Purifier custom definition ID and revision
    'definition_id'  => 'custom_definition', // Unique ID for caching purposes
    'definition_rev' => 1,                  // Increment this revision if you change the definition

    // Custom HTML element definitions
    'elements'       => [
        'link1'                => [
            'type'             => 'Inline',
            'contents'         => 'Inline',
            'attr_collections' => 'Common',
        ],
        'link2'                => [
            'type'             => 'Inline',
            'contents'         => 'Inline',
            'attr_collections' => 'Common',
        ],
        'link3'                => [
            'type'             => 'Inline',
            'contents'         => 'Inline',
            'attr_collections' => 'Common',
        ],
        'link4'                => [
            'type'             => 'Inline',
            'contents'         => 'Inline',
            'attr_collections' => 'Common',
        ],
        'link5'                => [
            'type'             => 'Inline',
            'contents'         => 'Inline',
            'attr_collections' => 'Common',
        ],
        'contact_support_link' => [
            'type'             => 'Inline',
            'contents'         => 'Inline',
            'attr_collections' => 'Common',
        ],
    ],
];

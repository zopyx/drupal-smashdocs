# drupal-smashdocs 

You must add this into "merge-plugin" composer.json Drupal file

"modules/contrib/*/composer.json"

Example:
"merge-plugin": {
    "include": [
        "core/composer.json",
        "modules/contrib/*/composer.json"
    ],
    "recurse": false,
    "replace": false,
    "merge-extra": false
},

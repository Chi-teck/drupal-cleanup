# drupal-cleanup
Composer plugin to remove files on Drupal packages

You can configure the plugin using the root package's composer.json extra field, like this:

```json
    "extra": {
        "drupal-cleanup": {
            "default": [
                "drupal-core": [
                    "modules/help",
                    "modules/history"
                ]
            ],
            "no-dev": {
                "drupal-core": [
                    "modules/*/tests",
                    "modules/*/src/Tests",
                    "profiles/demo_umami",
                    "profiles/*/tests",
                    "profiles/*testing*"
                ],
                "drupal-module": [
                    "tests",
                    "src/Tests"
                ],
            }
            "exclude": [
                "web/modules/contrib/devel/.spoons"
            ]
        }
    }
```

## Changes from `skilld-labs/drupal-cleanup`

Added modes for cleanup:

```diff
-extra.drupal-cleanup.[package-type]
+extra.drupal-cleanup.[mode].[package-type]
```

Modes are predefined:

- `default`: The default mode, executed always. Works as 
  `skilld-labs/drupal-cleanup`.
- `dev`: Only affected when composer is called in dev mode `--dev`.
- `no-dev`: Only affected when composer is called in no dev mode `--no-dev`.

Using these modes, you can have different clean-up results for your purposes.
E.g., most likely you want to keep some tests from Core in `dev` mode, but you
definitely do not need them in `no-dev` mode.

> [!NOTE]
> `exclude` still in place, it's a global configuration on the `mode` level.

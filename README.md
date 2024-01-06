# phead

A PHP code generator to generate code via your LAYOUT file.

[![Build Status](https://github.com/absszero/phead/workflows/build/badge.svg)](https://github.com/absszero/phead/actions)


## Installation

1. Install the package:
    ```shell
    composer global require absszero/phead
    ```

2. Set up Composer bin path:

    ### Linux / macOS

    ```bash shell
    # Bash shell
    echo 'export PATH="$PATH:~/.composer/vendor/bin"' >> ~/.bashrc
    source ~/.bashrc

    # Z shell
    echo 'export PATH="$PATH:~/.composer/vendor/bin"' >> ~/.zshrc
    source ~/.zshrc

    # Fish shell
    fish_add_path ~/.composer/vendor/bin
    ```

    ### Windows
     1. Right click on Start up -> System -> Advance system settings -> Environment variables -> System variables[below box] -> Select Path and click Edit.
     2. Click New and add this value `%USERPROFILE%\AppData\Roaming\Composer\vendor\bin`.


## Usage

### From Sample


```shell
# Get a sample layout file.
$ phead sample

my-layout.yaml is generated.

# Generate code via your layout file.
$ phead my-layout.yaml

Generating files...
Hello/MyController.php
Hello/MyModel.php
```

### Dry run

```shell
$ phead my-layout.yaml --dry

Generating files... (dry run)
Hello/MyController.php (skip)
Hello/MyModel.php (skip)
```

### Overwrite files

Existing files will not be regenerated, you can overwrite with `--force`.


```shell
$ phead my-layout.yaml --force

Generating files... (force)
Hello/MyController.php (overwrite)
Hello/MyModel.php (overwrite)
```

### Only those files

Only those file keys will be generated.

```shell
$ phead my-layout.yaml --only=model1,model2

Generating files...
Hello/MyModel1.php
Hello/MyModel2.php
```

## Layout

Layout file has two main parts:

- `$globals`: The global variables.
- `$files`: The files for auto generation.

```yaml
$globals:
  dir: Hello
  # Define a variable via environment variable
  user: '{{ $env.USER }}'

$files:
  # The file key
  model:

    # The file variables.
    # 'namespace', 'class' variables will be auto generated via 'to' path
    vars:
      foo: bar
      # Overwrite default namespace
      namespace: App\Hello

    # 'from', 'to' are required
    # 'from' can also be a stub file path,
    # EX. from: /my/stub/file.stub
    from: |
      <?php

      namespace {{ namespace }};

      class {{ class }}
      {
          public $foo = '{{ foo }}';
      }
    to: "{{ $globals.dir }}/MyModel.php"
  controller:
    from: |
      <?php

      namespace {{ namespace }};

      class {{ class }}
      {
          public index()
          {
            // {{ $files.model }} will be replaced with \App\Hello\MyModel
            $model = new {{ $files.model }};
          }
      }
    to: "{{ $globals.dir }}/MyController.php"
    # Append new methods to the class file
    methods:
      - |
        public function get()
        {
            return 'something';
        }

  test:
   # Skip the file
   skip: true
   from: |
      <?php

      namespace {{ namespace }};

      class {{ class }}
      {
          public testSomething()
          {
          }
      }
   to: "{{ $globals.dir }}/MyTest.php"
```
# NovaFieldJson

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gabrielesbaiz/nova-field-json.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/nova-field-json)
[![Total Downloads](https://img.shields.io/packagist/dt/gabrielesbaiz/nova-field-json.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/nova-field-json)

Customer Nova field for JSON data.

Original code from [armincms/json](https://github.com/armincms/json)

## Features

- ✅ Advanced Json support
- ✅ Basic usage
- ✅ Nested usage
- ✅ Action usage
- ✅ Showing / Hiding fields
- ✅ Save last values
- ✅ Separated data
- ✅ Fillable values
- ✅ Null values
- ✅ Autocasting

## Installation

You can install the package via composer:

```bash
composer require gabrielesbaiz/nova-field-json
```

## Usage

```php
use Gabrielesbaiz\NovaFieldJson\NovaFieldJson; 

  NovaFieldJson::make("ColumnName", [ 
      Select::make(__("Discount Type"), "type")
          ->options([
              'percent' => __('Percent'),
              'amount' => __('Amount'),
          ])->rules('required')->default('percent'),
      Number::make(__("Discount Value"), "value")
          ->rules("min:0")
          ->withMeta([
              'min' => 0
          ]),   
  ]),
```

### Nested Usage 
Storing nested data is very like straight data. just like the following; use the `Json` nested.

```php
use Gabrielesbaiz\NovaFieldJson\NovaFieldJson; 

  NovaFieldJson::make("ColumnName", [ 
      Select::make(__("Discount Type"), "type")
          ->options([
              'percent' => __('Percent'),
              'amount' => __('Amount'),
          ])->rules('required')->default('percent'),
      Number::make(__("Discount Value"), "value")
          ->rules("min:0")
          ->withMeta([
              'min' => 0
          ]),   
      // nested data
      NovaFieldJson::make("discount", [ 
        Select::make(__("Discount Type"), "type")
            ->options([
                'percent' => __('Percent'),
                'amount' => __('Amount'),
            ])->rules('required')->default('percent'),
        Number::make(__("Discount Value"), "value")
            ->rules("min:0")
            ->withMeta([
                'min' => 0
            ]),   
      ]),
  ]),

```

### Action Usage 
It is possible to use the `Json` in the `Action` like follow:

```php
use Gabrielesbaiz\NovaFieldJson\NovaFieldJson; 

class UpdateTime extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels; 


    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
      //
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields()
    {
        return collect([
            /// some fields
            
            NovaFieldJson::make(mb_strtolower($meal), [
                Text::make(__("From"), 'from')->rules('required'),
                Text::make(__("Until"), 'until')->rules('required'),  
                NovaFieldJson::make(mb_strtolower($meal), [
                    Text::make(__("From"), 'from'),
                    Text::make(__("Until"), 'until'),  
                ]),
            ]),

            /// some fields
        ])->map(function($field) {
            return $field instanceof NovaFieldJson ? $field->fields() : [$field];
        })->flatten()->all();
    }
}
```

### Showing And Hiding Fields
you can use the field `show/hide` methods on the JSON field. so this method will be called on each field under the `Json` field.The following example will hide all fields from the `index` view.

```php
use Gabrielesbaiz\NovaFieldJson\NovaFieldJson; 

  NovaFieldJson::make("ColumnName", [ 
       // fields
  ])->hideFromIndex(),

``` 

### Save Last Values 
By default; we clean the last data for store new data. but, it's possible to save the last data. for this, call the `saveHistory`  method on parent `Json` class. this causes us to overwrite the new data without clean the last data. see the follow:

```php
use Gabrielesbaiz\NovaFieldJson\NovaFieldJson; 
  
  NovaFieldJson::make("ColumnName", [ 
       // fields
  ])->saveHistory(),

``` 

### Separated Data
If you want store fields in one column but show in a separate place; you should make multiple `Json` field by one name.see the following:

```php
use Gabrielesbaiz\NovaFieldJson\NovaFieldJson; 

  NovaFieldJson::make("ColumnName", [ 
       // fields group 1
  ]),

  // other fields

  NovaFieldJson::make("ColumnName", [ 
       // fields group 2
  ])->saveHistory(),

``` 

* ATTENTION: at this situation, you should use `saveHistory` for next `Json` field. 

### Fill The Value
if you want to store the customized value of the field; you can use the `fillUsing` 
method and return custom value. see the follow:

* `fillUsing` accept three argumnets `$request`, `$attribute`, `$requestAttribute`.

```php
use Gabrielesbaiz\NovaFieldJson\NovaFieldJson; 
  
  NovaFieldJson::make("ColumnName", [ 
       Number::make(__("Discount Value"), "value")
            ->rules("min:0")
            ->withMeta([
                'min' => 0
            ])->fillUsing(function($request, $attribute, $requestAttribute) {
                if($request->exists($requestAttribute)) { 
                    return $request[$requestAttribute];
                }

                return 1000;
            }), 
  ]),
  
```

### Null Values
If there need to store some values as the `null`; you can use the `nullable` method that works like the Nova nullable. 
By default; nullable has the `true` value which means all values will be stored. But; It's possible to reject the storing of null values via passing the `false` value into the `nullable` method.

### Auto Casting
If not defined JSON casting for the field attribute; we will convert the field Value into JSON.
if you need disable this feature; use the `ignoreCasting` method;

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Armin Group](https://github.com/armincms)
- [Gabriele Sbaiz](https://github.com/gabrielesbaiz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

<?php
namespace Axelarge\FormFor;

use Axelarge\HtmlHelpers\Form;

/**
 * Form class to ease creating forms for domain models.
 *
 * Pass in a model object and call methods to generate inputs with ids, names and default values
 * <code>
 * $f = new Form(new Transport_Model, '/action-url');
 * echo $f->open();
 * echo $f->label('user_id');
 * echo $f->text_field('user_id');
 * echo $f->close();
 *
 * Output:
 * &lt;form action="/action-url" method="post"&gt;
 * &lt;label id="transport_user_id_label" for="transport_user_id"&gt;Hi from lang file&lt;/label&gt;
 * &lt;input type="text" id="transport_user_id" name="transport[user_id]" value="123"&gt;
 * &lt;/form&gt;
 * </code>
 */
class FormFor
{
    /** @var mixed */
    protected $model;
    /** @var string */
    protected $modelName;
    /** @var string */
    protected $name;
    /** @var string */
    protected $action;
    /** @var array */
    protected $options;
    /** @var \callable */
    protected $labelGetter;

    /** @var boolean */
    private $isNested;
    /** @var array Options array to pass when calling fieldsFor() */
    private $optionsForNested;


    /**
     * @param mixed $model
     * @param string $action
     * @param array $options
     */
    public function __construct($model = null, $action = '', array $options = array())
    {
        $this->model = $model;
        $this->action = $action;

        $this->modelName = static::modelName($model);
        if (isset($options['name'])) {
            $this->name = $options['name'];
            unset($options['name']);
        } else {
            $this->name = $this->modelName;
        }

        $this->optionsForNested = $options;

        $this->labelGetter = $this->arrDelete($options, 'label_getter', $this->createDefaultLabelGetter());
        $this->valueGetter = $this->arrDelete($options, 'value_getter', $this->createDefaultValueGetter());

        $this->options = $options;
    }

    /**
     * @see fieldsFor()
     * @static
     * @param string $name
     * @param object|object[] $models
     * @param string $parentName
     * @param array $options
     * @return FormFor|FormFor[]
     */
    public static function createFieldsFor($name, $models, $parentName = null, $options = array())
    {
        if (is_array($models)) {
            $result = array();
            foreach ($models as $idx => $model) {
                $theName = $parentName === null
                    ? "{$name}[$idx]"
                    : "{$parentName}[{$name}][$idx]";

                $form = new static($model, null, array_merge($options, array('name' => $theName)));
                $form->isNested = true;
                $result[$idx] = $form;
            }

            return $result;

        } else {
            if ($parentName !== null) {
                $name = "{$parentName}[{$name}]";
            }
            $form = new static($models, null, array_merge($options, array('name' => $name)));
            $form->isNested = true;

            return $form;
        }
    }

    protected function createDefaultLabelGetter()
    {
        $modelName = $this->modelName;
        /** @noinspection PhpUnusedParameterInspection */
        return function ($formFor, $attribute) use ($modelName)
        {
            /** @var $class FormFor */
            $class = __CLASS__;
            return $class::defaultLabelText($modelName, $attribute);
        };
    }

    protected function createDefaultValueGetter()
    {
        return function ($model, $attribute)
        {
            // TODO: Formatters for types

            /** @var $value mixed|\DateTime */
            $value = $model->{$attribute};
            return $value instanceof \DateTime
                ? $value->format('Y-m-d')
                : $value;
        };
    }

    public static function defaultLabelText($modelName, $attribute)
    {
        if (function_exists('__')) {
            $langKeys = array("model.{$modelName}.attributes.{$attribute}", "model.attributes.{$attribute}");
            foreach ($langKeys as $langKey) {
                $label = __($langKey);
                if ($label !== null && trim($label, '({[]})') !== $langKey) {
                    return $label;
                }
            }
        }

        return ucwords(preg_replace('/(?<=[a-z])(?=[A-Z])|[_-]/', ' ', $attribute));
    }

    /**
     * Returns the opening tag.
     *
     * Enables you to write
     * <code>
     * <?= $f = new FormFor($model) ?>
     * </code>
     *
     * @return string
     */
    public function __toString()
    {
        return $this->open();
    }

    /**
     * The form's opening tag
     *
     * @param array $extraOptions
     * @return string
     */
    public function open($extraOptions = array())
    {
        if ($this->isNested) {
            return null;
        }
        return Form::open($this->action, array_merge($this->options, $extraOptions));
    }

    /**
     * The form's closing tag
     *
     * @return string
     */
    public function close()
    {
        if ($this->isNested) {
            return null;
        }
        return Form::close();
    }

    /**
     * Creates a label for an input
     *
     * @see labelText()
     * @param string $name Name of the attribute
     * @param string $text The label text. Leave blank to get the value from labelText()
     * @param array $options HTML attributes
     * @return string
     */
    public function label($name, $text = null, array $options = array())
    {
        if ($text === null) {
            $text = $this->labelText($name);
        }

        return Form::label($text, $this->fieldName($name), $options);
    }

    /**
     * Creates a text field
     *
     * @param string $name Name of the attribute
     * @param array $attributes HTML attributes
     * @return string
     */
    public function text($name, array $attributes = array())
    {
        return Form::text($this->fieldName($name), $this->getValue($name), $attributes);
    }

    /**
     * Creates a password field.
     *
     * Does not set the value from model
     *
     * @param string $name
     * @param array $attributes HTML attributes
     * @return string
     */
    public function password($name, array $attributes = array())
    {
        return Form::password($this->fieldName($name), null, $attributes);
    }

    /**
     * Creates a hidden input field
     *
     * @param string $name Name of the attribute
     * @param array $attributes HTML attributes
     * @return string
     */
    public function hidden($name, array $attributes = array())
    {
        return Form::hidden($this->fieldName($name), $this->getValue($name), $attributes);
    }

    /**
     * Creates a textarea
     *
     * @param string $name Name of the attribute
     * @param array $attributes HTML attributes
     * @return string
     */
    public function textArea($name, array $attributes = array())
    {
        if (isset($attributes['value'])) {
            $text = $attributes['value'];
            unset($attributes['value']);
        } else {
            $text = $this->getValue($name);
        }

        return Form::textArea($this->fieldName($name), $text, $attributes);
    }

    /**
     * Creates a check box.
     * Also creates a hidden field with the value of 0, so that the field is present in $_POST even when not checked
     *
     * @param string $name Name of the attribute
     * @param array $attributes HTML attributes
     * @param bool|string $withHiddenField
     * @return string
     */
    public function checkBox($name, array $attributes = array(), $withHiddenField = true)
    {
        return Form::checkBox($this->fieldName($name), $this->getValue($name), 1, $attributes, $withHiddenField);
    }

    /**
     * Creates multiple checkboxes for a has-many association.
     *
     * @param $name
     * @param array $collection
     * @param array $labelAttributes
     * @param bool $returnAsArray
     * @return string
     */
    public function collectionCheckBoxes($name, array $collection, array $labelAttributes = array(), $returnAsArray = false)
    {
        return Form::collectionCheckBoxes($this->fieldName($name), $collection, $this->getValue($name), $labelAttributes, $returnAsArray);
    }

    /**
     * Creates a radio button
     *
     * @param string $name
     * @param mixed $value
     * @param array $attributes
     * @return string
     */
    public function radio($name, $value, array $attributes = array())
    {
        return Form::radio($this->fieldName($name), $value, $this->getValue($name) === $value, $attributes);
    }

    /**
     * Creates multiple radio buttons with labels
     *
     * @param string $name
     * @param array $collection
     * @param array $labelAttributes
     * @param bool $returnAsArray
     * @return array|string
     */
    public function collectionRadios($name, array $collection, array $labelAttributes = array(), $returnAsArray = false)
    {
        return Form::collectionRadios($this->fieldName($name), $collection, $this->getValue($name), $labelAttributes, $returnAsArray);
    }

    /**
     * Creates a select tag
     * <code>
     * $f->select('coffee_id', array('b' => 'black', 'w' => 'white'));
     * </code>
     *
     * @param string $name Name of the attribute
     * @param array $collection An associative array used for the option values
     * @param array $attributes HTML attributes
     * @return string
     */
    public function select($name, array $collection, array $attributes = array())
    {
        // TODO: Include default, include blank
        return Form::select($this->fieldName($name), $collection, $this->getValue($name), $attributes);
    }

    public function button($name, $text, array $attributes = array())
    {
        return Form::button($this->fieldName($name), $text, $attributes);
    }

    /**
     * Create a nested form for a relation
     *
     * Supports single or multiple associations
     *
     * For a single association a new proxy object is returned with all FormFor methods:
     * <code>
     * $f2 = $f->fieldsFor('author');
     * echo $f2->text('name');
     * //=> '&lt;input type="text" name="model[author][name]" ...&gt;'
     * </code>
     *
     * For a multiple association an array of proxy objects is returned.
     * Each association field is indexed by the keys of the association array to group relevant fields together.
     * It's usually a good idea to include the related model's id as a hidden field.
     * <code>
     * foreach ($f->fieldsFor('friends') as $friend) {
     *     echo $friend->hidden('id');
     *     echo $friend->text('name');
     * }
     * //=> '&lt;input type="hidden" name="model[friends][0][id]" value="123"&gt;
     * //=> '&lt;input type="text" name="model[friends][0][name]" value="foo"&gt;
     * </code>
     *
     * A model or array of models can be passed manually by using the second parameter.
     * <code>
     * $f3 = $f->fieldsFor('association', $myOtherModel);
     * </code>
     *
     * @param string $name
     * @param mixed|array $models
     * @param array $options
     * @return FormFor|FormFor[]
     */
    public function fieldsFor($name, $models = null, array $options = array())
    {
        if ($models === null) {
            $models = $this->getValue($name);
        }

        return static::createFieldsFor($name, $models, $this->name, array_merge($this->optionsForNested, $options));
    }

    /**
     * Generates an ID for use with labels and input fields.
     * Consists of model name and field name, joined with an underscore
     * <code>
     * $f = new Form(new Transport());
     * $f->field_id('driver_id') //=> "transport-driver_id"
     * </code>
     *
     * @param string $name
     * @return string
     */
    protected function fieldId($name)
    {
        return Form::autoId($this->fieldName($name));
    }

    /**
     * Generates the name for a field
     * <code>
     * $f = new Form(new Transport_Model());
     * $f->field_name('driver_id') //=> "transport[driver_id]"
     * </code>
     *
     * @param string $name
     * @return string
     */
    protected function fieldName($name)
    {
        return $this->name . '[' . $name . ']';
    }

    /**
     * Get attribute value from model
     *
     * @param string $attribute Name of the attribute
     * @return mixed
     */
    public function getValue($attribute)
    {
        /** @var $getter \callable */
        $getter = $this->valueGetter;
        return $getter($this->model, $attribute);
    }

    /**
     * Returns the label text for an attribute
     *
     * @param string $attribute Name of the attribute
     * @return string
     */
    public function labelText($attribute)
    {
        $getter = $this->labelGetter;
        return $getter($this, $attribute);
    }

    /**
     * Returns the base field name for a model
     *
     * @static
     * @param object $model
     * @return string
     */
    public static function modelName($model)
    {
        $name = get_class($model);

        if (($lastSlash = strrpos($name, '\\')) !== false) { // Strip namespace
            $name = substr($name, $lastSlash + 1);
        }
        $name = preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', $name); // Camel case to underscores
        $name = strtolower($name);

        return $name;
    }

    /**
     * Returns the underlying model
     *
     * @return object
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Deletes a key from an array and returns the value
     *
     * Returns $default if the key is not set
     *
     * @param array $arr
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    private function arrDelete(array &$arr, $key, $default = null)
    {
        if (!isset($arr[$key])) {
            return $default;
        }

        $value = $arr[$key];
        unset($arr[$key]);

        return $value;
    }

}

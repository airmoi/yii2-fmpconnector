<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator airmoi\yii2fmconnector\gii\api\nuxt\Generator */

$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();

$modelId = Inflector::camel2id(StringHelper::basename($generator->modelClass));
$controllerId = Inflector::camel2id(StringHelper::basename($generator->getControllerID()));
$singular = strtoupper(Inflector::singularize($modelId));
$plural = strtoupper(Inflector::pluralize($modelId));

$componentName = ucfirst($controllerId) . 'Form';

$tableSchema = $generator->getTableSchema();
$attributes = $generator->getColumnNames();

$formInputs = $generator->generateForm();

$body = <<<JS
<template>
  <v-card>
    <v-card-title class="headline grey lighten-2">
      {{ title }}
    </v-card-title>
    <v-card-text>
$formInputs
    </v-card-text>
    <v-card-actions>
      <v-spacer></v-spacer>
      <v-btn color="blue darken-1" text @click="close"> Cancel</v-btn>
      <v-btn color="blue darken-1" text @click="save"> Save</v-btn>
    </v-card-actions>
  </v-card>
</template>

<script>
export default {
  name: '{$componentName}',
  props: {
    title: null,
    record: {},
  },
  data: () => ({
    form: {},
  }),
  watch: {
    record(val) {
      this.form = { ...val };
    },
  },
  methods: {
    save() {
      this.\$emit('save', this.form);
    },
    close() {
      this.\$emit('close');
    },
  },
};
</script>

<style scoped></style>
JS;
echo $body;
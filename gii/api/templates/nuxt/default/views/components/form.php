<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\crud\Generator */

$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();

$modelId = Inflector::camel2id(StringHelper::basename($generator->modelClass));
$controllerId = Inflector::camel2id(StringHelper::basename($generator->getControllerID()));
$singular = strtoupper(Inflector::singularize($modelId));
$plural = strtoupper(Inflector::pluralize($modelId));

$tableSchema = $generator->getTableSchema();
$attributes = $generator->getColumnNames();

$body = <<<JS
<template>
  <v-card>
    <v-card-title class="headline grey lighten-2">
      {{ title }}
    </v-card-title>
    <v-card-text>
      TODO create form columns
    </v-card-text>
  </v-card>
</template>

<script>
export default {
  name: '{$controllerId}Form',
  data: () => ({
    record: {},
  }),
  watch: {
  },
  methods: {
  },
};
</script>

<style scoped></style>
JS;
echo $body;
# CDS Connect Application Programming Interface (API)

## About

The CDS Connect API is a [Drupal 8 module](https://www.drupal.org/docs/8/creating-custom-modules) written in the PHP programming language. The module defines a [JSON schema](https://json-schema.org/) that represents Clinical Decision Support (CDS) [artifacts](https://cds.ahrq.gov/cdsconnect/artifact) as defined on [CDS Connect](https://cds.ahrq.gov/).

The CDS Connect API is part of the [CDS Connect](https://cds.ahrq.gov/cdsconnect) project, sponsored by the [Agency for Healthcare Research and Quality](https://www.ahrq.gov/) (AHRQ), and developed under contract with AHRQ by [MITRE's CAMH](https://www.mitre.org/centers/cms-alliances-to-modernize-healthcare/who-we-are) FFRDC.

## Contributions

For information about contributing to this project, please see [CONTRIBUTING](CONTRIBUTING.md).

## Development Details

The module also provides a [Representational State Transfer (REST)](https://en.wikipedia.org/wiki/Representational_state_transfer) endpoint for accessing, creating, and modifying CDS artifacts. This is accomplished by creating a [Drupal Plugin](https://www.drupal.org/node/2087839) for the [REST module](https://www.drupal.org/docs/8/core/modules/rest) in Drupal Core. Authentication, routing, database access, and permissions are all handled by Drupal; the sole purpose of the CDS Connect API is to expose the CDS artifact [content type](https://www.drupal.org/docs/8/administering-drupal-8-site/managing-content-0/working-with-content-types-and-fields) in a format which is easier for non-Drupal experts to work with.

The majority of the software in the CDS Connect API is for mapping CDS artifacts between the Drupal representation and the CDS JSON schema. Ancillary functionalities include sanitization and validation of the data passing in and out of it.

## Contents


## Usage


## LICENSE

Copyright 2018 Agency for Healthcare Research and Quality

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

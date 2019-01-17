# CDS Connect Application Programming Interface (API)

## About

The CDS Connect API is a [Drupal 8 module](https://www.drupal.org/docs/8/creating-custom-modules) written in the PHP programming language. The module defines a [JSON schema](https://json-schema.org/) that represents Clinical Decision Support (CDS) [artifacts](https://cds.ahrq.gov/cdsconnect/artifact) as found on [CDS Connect](https://cds.ahrq.gov/). The module also provides a [Representational State Transfer (REST)](https://en.wikipedia.org/wiki/Representational_state_transfer) endpoint for accessing, creating, and modifying CDS artifacts on CDS Connect.

The CDS Connect API is part of the [CDS Connect](https://cds.ahrq.gov/cdsconnect) project, sponsored by the [Agency for Healthcare Research and Quality](https://www.ahrq.gov/) (AHRQ), and developed under contract with AHRQ by [MITRE's CAMH](https://www.mitre.org/centers/cms-alliances-to-modernize-healthcare/who-we-are) FFRDC.

## Contributions

For information about contributing to this project, please see [CONTRIBUTING](CONTRIBUTING.md).

## Development Details

 The CDS Connect API is implemented through the creation of a [Drupal Plugin](https://www.drupal.org/node/2087839) for the [REST module](https://www.drupal.org/docs/8/core/modules/rest) in Drupal Core. The majority of the software in the CDS Connect API is for mapping CDS artifacts between the Drupal representation and the CDS JSON schema. Ancillary functionalities include sanitization and validation of the data passing in and out of it.

 Authentication, routing, database access, and permissions are all handled by Drupal; the sole purpose of the CDS Connect API is to expose the CDS artifact [content type](https://www.drupal.org/docs/8/administering-drupal-8-site/managing-content-0/working-with-content-types-and-fields) in a format which is hoped to be easier for non-Drupal experts to work with.

## Contents

The CDS Connect API module consists of three directories:

* `doc`: Contains additional documentation.
* `modules`: Contains a sub-module for installing a local copy of the CDS Connect `artifact` [node bundle](https://www.drupal.org/docs/8/api/entity-api/bundles).
* `src`: Contains the source code for the CDS Connect API, which is a [Plugin](https://www.drupal.org/node/2087839) to the [REST module](https://www.drupal.org/docs/8/core/modules/rest) in Drupal Core.
* `tests`: Contains [PHPUnit](https://github.com/sebastianbergmann/phpunit) tests and fixtures.

## Installation

The CDS Connect API can be installed in any Drupal 8 instance as a [custom module](https://www.drupal.org/docs/8/creating-custom-modules). The `cds_api.info.yml` file specifies the required Drupal dependencies for the CDS Connect API module:

* [REST](https://www.drupal.org/docs/8/core/modules/rest)
* [Paragraphs](https://www.drupal.org/project/paragraphs)
* [Taxonomy](https://www.drupal.org/docs/8/core/modules/taxonomy)

In addition, the [json-schema](https://github.com/justinrainbow/json-schema) vendor library is required, since it is leveraged to provide validation of incoming JSON payloads. Additional documentation can be found in the `doc` folder.

## LICENSE

Copyright 2019 Agency for Healthcare Research and Quality.

Licensed under the GNU General Public License, version 2 or later;
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

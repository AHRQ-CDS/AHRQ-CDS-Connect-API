# CDS Connect API Usage

## Installation

The CDS Connect API can be installed in the same manner as any other Drupal 8 module. The `cds_api.info.yml` specifies the required Drupal dependencies. In addition, the [json-schema](https://github.com/justinrainbow/json-schema) vendor library is required; as described in the [json-schema README file](https://github.com/justinrainbow/json-schema/blob/master/README.md) it can be installed either via git or [composer](https://getcomposer.org/).

The [REST UI module](https://www.drupal.org/project/restui) is not required, but it makes enabling and configuring the RESTful endpoints provided by the CDS Connect API much easier.

The CDS Connect API assumes that a CDS artifact content type has been defined in the Drupal instance in which it is installed. A module that installs the CDS Connect artifact content type is included with the test fixtures (discussed below).

## Tests

A number of [PHPUnit](https://github.com/sebastianbergmann/phpunit) tests have been included to ensure that the code functions correctly. Due to how Drupal incorporates PHPUnit, the tests will only run after the module has been installed in a local Drupal instance. For more information on running PHPUnit with Drupal projects, see [this page](https://www.drupal.org/docs/8/phpunit).

Most tests in the `tests` folder are quick-running unit tests which focus on the JSON schema validation and input sanitization. In addition, there are a smaller number of kernel tests which involve setting up a local testing database and a bare-bone version of Drupal. Due to their more complex nature, the kernel tests can take up to several minutes to run. For more information on running kernel tests with Drupal, see [this page](https://www.drupal.org/docs/8/phpunit/running-phpunit-tests).

### CDS Artifact Content Type

The kernel tests require the CDS artifact content type be installed, since they involve defining and saving CDS artifacts to the test database. To help with testing, a custom module has been created which defines an `artifact` content type upon installation. PHPUnit installs this module prior to running any of the kernel tests.

This custom module, located in the `tests/modules/cds_artifact_type` directory. If desired, this module can be installed separately from the CDS Connect API module to provide an `artifact` content type in a Drupal installation.

## Access Control and Authentication

[Permissions](https://api.drupal.org/api/drupal/core%21core.api.php/group/user_api/8.5.x) in Drupal are based upon user roles (_e.g._ administrator). After installation of the CDS Connect API module, a set of permissions will be exposed in the Drupal administrator user interface (UI) for each of the REST resources defined by the module. This allows system level access checks to be put into place for each of the REST resources. As a secondary level of access control, the CDS Connect API also makes user permission checks prior to sharing, creating, or modifying `artifact` content.

An overview of authentication with Drupal is given [here](https://www.drupal.org/docs/8/modules/json-api/what-json-api-doesnt-do). In summary, users POST to `/user/login?_format=json` to login, and POST to `/user/logout?_format=json` to logout. Upon successful authentication, the response to the login POST returns a cross-site request forgery (CSRF) token. This token must be included in all following requests.

Login and logout requests using [curl](https://curl.haxx.se/) would appear as follows:

```
curl https://cds.ahrq.gov/user/login?_format=json --header "Content-Type:application/json" --request POST --data "{"name":"USERNAME","pass":"USERPASSWORD"}" -c cookie.txt
```

```
curl https://cds.ahrq.gov/user/logout?_format=json --header "Content-Type:application/json" --header "X-CSRF-Token:THETOKEN" -b cookie.txt
```

## Functionality

The CDS Connect API provides the following RESTful functionality for CDS Connect:

* `GET` the JSON schema for the CDS artifact content type.
* `GET` a particular artifact (by node ID) in the CDS artifact JSON schema.
* `POST` a new artifact using the CDS artifact JSON schema.
* `PATCH` an existing aritfact (by node ID) using the CDS artifact JSON schema.

The `cds_api.yaml` file in this directory describes these resources in more detail using the [OpenAPI](https://www.openapis.org/) specification.

### File Attachments

Due to limitations in the Drupal before version 8.6, file attachments must be handled separately from CDS Connect API calls. While a more [streamlined approach](https://www.drupal.org/node/2941420) is possible in the future, this section outlines the [current procedure](https://www.drupal.org/docs/8/modules/json-api/creating-new-resources-post) using the [JSON API File module](https://www.drupal.org/project/jsonapi_file):

1. `POST` file as base64 encoded data to `/jsonapi/file/zip`
  * Headers should include cookie and CSRF token
2. `GET` the node ID of the CDS artifact that the file will be attached to.
  * This can be accomplished using the CDS Connect API
3. `GET` the ID of the paragraph in the targeted artifact
  * Use `/node/$id` endpoint enabled by REST module
4. `PATCH` the paragraph to point to the new file attachment
  * Use the `/entity/paragraph/$id` endpoint enabled by the REST module

Note that this approach to file attachments requires the following Drupal modules:

* JSON API
* JSON API File
* File Entity

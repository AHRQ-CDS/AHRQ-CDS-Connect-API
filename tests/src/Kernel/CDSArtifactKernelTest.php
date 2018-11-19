<?php

namespace Drupal\Tests\cds_api\Kernel;

use Drupal\Tests\cds_api\Unit\CDSUtils;

use Drupal\cds_api\Plugin\rest\resource\CDSArtifact;
use Drupal\cds_api\Plugin\rest\resource\CDSResource;
use Drupal\KernelTests\KernelTestBase;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use Drupal\Core\DependencyInjection\ContainerBuilder;


/**
 * CDSArtifactKernelTests
 * @group restKernel
 */
class CDSArtifactKernelTest extends EntityKernelTestBase {

  protected $testUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node','paragraphs','taxonomy','datetime','file','image',
  'link','filefield_paths','entity_reference_revisions','menu_ui','field_ui',
  'field_group','cds_artifact_type','cds_api'];

  /**
   * constant to help us use local test input/output files
   * NOTE: ASSUMES phpunit test is conducted at the projectroot directory, as specified in the
   *  dev notes
   * TODO: candidate to refactor to a test utiliteis file/class
   *          or even to a CDS_API_TestCase that this class then derive from
   */

  /**
   * A simple artifact.
   *
   * @var \Drupal\cds_api\Plugin\rest\resource\CDSArtifact
   */
  protected $simple_artifact;

  protected function setUp() {
    parent::setUp();

    $this->testUser = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
    ]);
    $this->testUser->save();
    \Drupal::service('current_user')->setAccount($this->testUser);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('paragraph');

    $this->installModule('cds_artifact_type');

    $this->installConfig('filefield_paths');
    $this->installConfig('cds_artifact_type');

    // Setup a simple artifact.
    $json = CDSUtils::read_json("request_minimal.json");
    $this->simple_artifact = new CDSArtifact();
    $this->simple_artifact->load_json($json);
  }

  /** @test
   * Test to ensure that a very basic artifact node is saved correctly.
  */
  public function testSimpleSave() {
    $node = Node::create([
      'type' => 'artifact',
      'moderation_state' => 'draft',
      'title' => 'title'
    ]);
    $node->save();
    $nid = $node->id();
    $new_node = Node::load($nid);
    $this->assertEquals($new_node->getTitle(), 'title');
  }

  /** @test
   * Test to ensure that the simple_artifact is saved correctly.
  */
  public function testJsonSave() {
    $node = $this->simple_artifact->get_as_node();
    $nid = $node->id();
    $new_node = Node::load($nid);
    $this->assertEquals($new_node->getTitle(), 'title (test 0 + 1 )');
  }

  /** @test
   * Test to ensure that SQL injection does not execute when inserted into the database.
  */
  public function testSqlInjection() {
    $sqlinjection = 'SELECT * FROM users WHERE uid = 1; DROP TABLE node; DROP TABLE users;';
    $node = Node::create([
      'type' => 'artifact',
      'moderation_state' => 'draft',
      'title' => 'title',
      'field_description' => $sqlinjection
    ]);
    $node->save();
    $nid = $node->id();

    // First verify that the attempted SQL injection has made it into the database.
    $result = db_select('node__field_description', 'n')
      ->fields('n')
      ->condition('n.entity_id', $nid)
      ->execute()
      ->fetchAll();
    $this->assertEquals($result[0]->field_description_value, $sqlinjection);

    // Then verify that the node table still exists despite the attempted SQL injection.
    $result = db_select('node', 'n')
      ->fields('n')
      ->condition('n.nid', $nid)
      ->execute()
      ->fetchAllAssoc('type');
    $this->assertEquals($result['artifact']->type, 'artifact');

    // Load the node from the database and verify again that the attempted SQL is there.
    $new_node = Node::load($nid);
    $this->assertEquals($new_node->get('field_description')->getValue()[0]["value"], $sqlinjection);
  }

  // /** @test
  // * 
  // */
  // public function test_get_schema() {

  //   $resource = new CDSResource();
  //   $gottenSchema = $resource->get();
      
  //   $storedSchema = CDSArtifact::get_schema();

  //   $this->assertJsonStringEqualsJsonString( json_encode( $storedSchema ), json_encode( $gottenSchema ) );
  // }

}

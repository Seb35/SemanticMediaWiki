<?php

namespace SMW\Tests\Property\Constraint\Constraints;

use SMW\DataItemFactory;
use SMW\Property\Constraint\Constraints\UniqueValueConstraint;
use SMW\Property\Constraint\ConstraintError;
use SMW\Tests\TestEnvironment;
use SMW\Tests\PHPUnitCompat;

/**
 * @covers \SMW\Property\Constraint\Constraints\UniqueValueConstraint
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 3.1
 *
 * @author mwjames
 */
class UniqueValueConstraintTest extends \PHPUnit_Framework_TestCase {

	use PHPUnitCompat;

	private $testEnvironment;
	private $dataItemFactory;
	private $propertySpecificationLookup;
	private $store;
	private $entityValueUniquenessConstraintChecker;

	protected function setUp() {
		$this->testEnvironment = new TestEnvironment();
		$this->dataItemFactory = new DataItemFactory();

		$this->entityValueUniquenessConstraintChecker = $this->getMockBuilder( '\SMW\SQLStore\EntityValueUniquenessConstraintChecker' )
			->disableOriginalConstructor()
			->getMock();

		$this->store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->setMethods( [ 'service' ] )
			->getMockForAbstractClass();

		$this->store->expects( $this->any() )
			->method( 'service' )
			->with( $this->equalTo( 'EntityValueUniquenessConstraintChecker' ) )
			->will( $this->returnValue( $this->entityValueUniquenessConstraintChecker ) );

		$this->propertySpecificationLookup = $this->getMockBuilder( '\SMW\PropertySpecificationLookup' )
			->disableOriginalConstructor()
			->getMock();

		$this->testEnvironment->registerObject( 'PropertySpecificationLookup', $this->propertySpecificationLookup );
	}

	protected function tearDown() {
		$this->testEnvironment->tearDown();
	}

	public function testCanConstruct() {

		$this->assertInstanceOf(
			UniqueValueConstraint::class,
			new UniqueValueConstraint( $this->store, $this->propertySpecificationLookup )
		);
	}

	public function testInvalidValueThrowsException() {

		$instance = new UniqueValueConstraint(
			$this->store,
			$this->propertySpecificationLookup
		);

		$this->setExpectedException( '\RuntimeException' );
		$instance->checkConstraint( [], 'Foo' );
	}

	public function testCanNotValidateOnNull() {

		$dataValue = $this->getMockBuilder( '\SMWDataValue' )
			->disableOriginalConstructor()
			->setMethods( [ 'getProperty', 'getDataItem', 'getContextPage' ] )
			->getMockForAbstractClass();

		$instance = new UniqueValueConstraint(
			$this->store,
			$this->propertySpecificationLookup
		);

		$instance->checkConstraint( [], $dataValue );

		$this->assertFalse(
			$instance->hasViolation()
		);
	}

	public function testValidate_HasNoConstraintViolation() {

		$property = $this->dataItemFactory->newDIProperty( __METHOD__ );

		$this->entityValueUniquenessConstraintChecker->expects( $this->atLeastOnce() )
			->method( 'checkConstraint' )
			->will( $this->returnValue( [] ) );

		$dataValue = $this->getMockBuilder( '\SMWDataValue' )
			->disableOriginalConstructor()
			->setMethods( [ 'getProperty', 'getDataItem', 'getContextPage' ] )
			->getMockForAbstractClass();

		$dataValue->expects( $this->atLeastOnce() )
			->method( 'getContextPage' )
			->will( $this->returnValue( $this->dataItemFactory->newDIWikiPage( 'UV', NS_MAIN ) ) );

		$dataValue->expects( $this->atLeastOnce() )
			->method( 'getProperty' )
			->will( $this->returnValue( $property ) );

		$dataValue->expects( $this->atLeastOnce() )
			->method( 'getDataItem' )
			->will( $this->returnValue( $this->dataItemFactory->newDIBlob( 'Foo' ) ) );

		$instance = new UniqueValueConstraint(
			$this->store,
			$this->propertySpecificationLookup
		);

		$instance->checkConstraint( [ 'unique_value_constraint' => true ], $dataValue );

		$this->assertFalse(
			$instance->hasViolation()
		);
	}

	public function testValidate_HasConstraintViolation() {

		$property = $this->dataItemFactory->newDIProperty( __METHOD__ );

		$error = new ConstraintError(
			[ 'smw-datavalue-constraint-uniqueness-violation', $property->getLabel(), '...', 'Foo' ]
		);

		$this->entityValueUniquenessConstraintChecker->expects( $this->atLeastOnce() )
			->method( 'checkConstraint' )
			->will( $this->returnValue( [ $this->dataItemFactory->newDIWikiPage( 'Foo', NS_MAIN ) ] ) );

		$dataValue = $this->getMockBuilder( '\SMWDataValue' )
			->disableOriginalConstructor()
			->setMethods( [ 'getProperty', 'getDataItem', 'getContextPage', 'addErrorMsg' ] )
			->getMockForAbstractClass();

		$dataValue->expects( $this->atLeastOnce() )
			->method( 'getContextPage' )
			->will( $this->returnValue( $this->dataItemFactory->newDIWikiPage( 'UV', NS_MAIN ) ) );

		$dataValue->expects( $this->atLeastOnce() )
			->method( 'getProperty' )
			->will( $this->returnValue( $property ) );

		$dataValue->expects( $this->atLeastOnce() )
			->method( 'addErrorMsg' )
			->with( $this->equalTo( $error ) );

		$dataValue->expects( $this->atLeastOnce() )
			->method( 'getDataItem' )
			->will( $this->returnValue( $this->dataItemFactory->newDIBlob( 'Foo' ) ) );

		$instance = new UniqueValueConstraint(
			$this->store,
			$this->propertySpecificationLookup
		);

		$instance->checkConstraint( [ 'unique_value_constraint' => true ], $dataValue );

		$this->assertTrue(
			$instance->hasViolation()
		);
	}

}
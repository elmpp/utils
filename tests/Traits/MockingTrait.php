<?php

namespace Partridge\Utils\Tests\Traits;

trait MockingTrait {

    /**
 * Setup methods required to mock an iterator
 *
 *  - https://stackoverflow.com/a/15907250/2968327
 * 
 * @param \PHPUnit_Framework_MockObject_MockObject $iteratorMock The mock to attach the iterator methods to
 * @param array $items The mock data we're going to use with the iterator
 * @return \PHPUnit_Framework_MockObject_MockObject The iterator mock
 */
  protected function mockIterator(\PHPUnit_Framework_MockObject_MockObject $iteratorMock, array $items)
  {
      $iteratorData = new \stdClass();
      $iteratorData->array = $items;
      $iteratorData->position = 0;
  
      $iteratorMock->expects($this->any())
                   ->method('rewind')
                   ->will(
                       $this->returnCallback(
                           function() use ($iteratorData) {
                               $iteratorData->position = 0;
                           }
                       )
                   );
  
      $iteratorMock->expects($this->any())
                   ->method('current')
                   ->will(
                       $this->returnCallback(
                           function() use ($iteratorData) {
                               return $iteratorData->array[$iteratorData->position];
                           }
                       )
                   );
  
      $iteratorMock->expects($this->any())
                   ->method('key')
                   ->will(
                       $this->returnCallback(
                           function() use ($iteratorData) {
                               return $iteratorData->position;
                           }
                       )
                   );
  
      $iteratorMock->expects($this->any())
                   ->method('next')
                   ->will(
                       $this->returnCallback(
                           function() use ($iteratorData) {
                               $iteratorData->position++;
                           }
                       )
                   );
  
      $iteratorMock->expects($this->any())
                   ->method('valid')
                   ->will(
                       $this->returnCallback(
                           function() use ($iteratorData) {
                               return isset($iteratorData->array[$iteratorData->position]);
                           }
                       )
                   );
  
      // $iteratorMock->expects($this->any())
      //              ->method('count')
      //              ->will(
      //                  $this->returnCallback(
      //                      function() use ($iteratorData) {
      //                          return sizeof($iteratorData->array);
      //                      }
      //                  )
      //              );
  
      return $iteratorMock;
  }
}
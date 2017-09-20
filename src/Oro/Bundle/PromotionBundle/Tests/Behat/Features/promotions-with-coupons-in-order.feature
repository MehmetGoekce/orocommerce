@fixture-OroPromotionBundle:promotions-with-coupons.yml
Feature: Promotions with coupons in Order page
  In order to manage promotions with coupons in order page
  As administrator
  I need to have ability to add, remove and disable applied promotions by coupons on order edit page

  Scenario: Coupon applying on order edit page
    Given I login as administrator
    And go to Sales / Orders
    And click edit SimpleOrder in grid
    And click "Promotions and Discounts"
    When I click "Add Coupon Code"
    And type "test-1" in "Coupon Code"
    Then I should see a "Highlighted Suggestion" element
    When I click on "Highlighted Suggestion"
    And click "Add" in modal window
    Then I should see next rows in "Added Coupons" table
      | Coupon Code | Promotion                    | Type      | Discount Value |
      | test-1      | Line Item Discount Promotion | Line Item | $1.00          |
    When click "Apply" in modal window
    Then I should see next rows in "All Promotions" table
      | Code   | Promotion                    | Type      | Status | Discount |
      | test-1 | Line Item Discount Promotion | Line Item | Active |  -$10.00 |
    And I see next subtotals for "Backend Order":
      | Subtotal | Amount  |
      | Subtotal | $50.00  |
      | Discount | -$10.00 |
      | Total    | $40.00  |
    When I save form
    And click "Save" in modal window
    Then I see next subtotals for "Backend Order":
      | Subtotal | Amount  |
      | Subtotal | $50.00  |
      | Discount | -$10.00 |
      | Total    | $40.00  |

  Scenario: Delete added coupon from grid
    When I click "Promotions and Discounts"
    And I click on Remove action for "Line Item Discount Promotion" row in "All Promotions" table
    Then I should see no records in "All Promotions" table
    And see next subtotals for "Backend Order":
      | Subtotal | Amount |
      | Subtotal | $50.00 |
      | Total    | $50.00 |
    When I save form
    And click "Save" in modal window
    Then I see next subtotals for "Backend Order":
      | Subtotal | Amount  |
      | Subtotal | $50.00  |
      | Total    | $50.00  |

  Scenario: Coupon applying on order view page
    When go to Sales / Orders
    And click view SimpleOrder in grid
    And I click "Add Coupon Code"
    And type "test-1" in "Coupon Code"
    Then I should see a "Highlighted Suggestion" element
    When click on "Highlighted Suggestion"
    And click "Add" in modal window
    Then I should see next rows in "Added Coupons" table
      | Coupon Code | Promotion                    | Type      | Discount Value |
      | test-1      | Line Item Discount Promotion | Line Item | $1.00          |
    When click "Apply" in modal window
    Then I should see next rows in "All Promotions" table
      | Code   | Promotion                    | Type      | Status | Discount |
      | test-1 | Line Item Discount Promotion | Line Item | Active |  -$10.00 |
    And I see next subtotals for "Backend Order":
      | Subtotal | Amount  |
      | Subtotal | $50.00  |
      | Discount | -$10.00 |
      | Total    | $40.00  |

  Scenario: "Cancel" button do not save selected coupons
    When go to Sales / Orders
    And click view SecondOrder in grid
    And I click "Add Coupon Code"
    And type "test-1" in "Coupon Code"
    Then I should see a "Highlighted Suggestion" element
    When click on "Highlighted Suggestion"
    And click "Add" in modal window
    Then I should see next rows in "Added Coupons" table
      | Coupon Code | Promotion                    | Type      | Discount Value |
      | test-1      | Line Item Discount Promotion | Line Item | $1.00          |
    And I click "Cancel"
    Then I should see no records in "All Promotions" table

  Scenario: Deactivate button in All Promotions grid
    When go to Sales / Orders
    And click edit SimpleOrder in grid
    And click "Promotions and Discounts"
    And I click on Deactivate action for "Line Item Discount Promotion" row in "All Promotions" table
    Then I should see next rows in "All Promotions" table
      | Code   | Promotion                    | Type      | Status   | Discount |
      | test-1 | Line Item Discount Promotion | Line Item | Inactive | $0.00    |
    And I see next subtotals for "Backend Order":
      | Subtotal | Amount |
      | Subtotal | $50.00 |
      | Total    | $50.00 |

  Scenario: Activate button in All Promotions grid
    When I click on Activate action for "Line Item Discount Promotion" row in "All Promotions" table
    Then I should see next rows in "All Promotions" table
      | Code   | Promotion                    | Type      | Status | Discount |
      | test-1 | Line Item Discount Promotion | Line Item | Active | -$10.00  |
    And I see next subtotals for "Backend Order":
      | Subtotal | Amount  |
      | Subtotal | $50.00  |
      | Discount | -$10.00 |
      | Total    | $40.00  |

  Scenario: If Line item was deleted from order, applied Line Item promotion is deleted from order
    When I click delete backend order line item "AA1"
    Then I should see no records in "All Promotions" table
    And I see next subtotals for "Backend Order":
      | Subtotal | Amount |
      | Total    | $0.00  |

  Scenario: Product was changed in order
    When I click "Cancel"
    And click edit SimpleOrder in grid
    Then I should see next rows in "All Promotions" table
      | Code   | Promotion                    | Type      | Status | Discount |
      | test-1 | Line Item Discount Promotion | Line Item | Active | -$10.00  |
    And I see next subtotals for "Backend Order":
      | Subtotal | Amount  |
      | Subtotal | $50.00  |
      | Discount | -$10.00 |
      | Total    | $40.00  |
    And I set "XX1" Product for first backend order line item
    Then I should see a "Highlighted Suggestion" element
    When I click on "Highlighted Suggestion"
    Then I should see no records in "All Promotions" table
    And I see next subtotals for "Backend Order":
      | Subtotal | Amount |
      | Total    | $0.00  |

  Scenario: Promotion is applied, Order was saved. Promotion was deleted, order promotions is not changed
    When I click "Cancel"
    And I go to Marketing / Promotions / Promotions
    And click delete Line Item Discount Promotion in grid
    And I confirm deletion
    And go to Sales / Orders
    And click edit SimpleOrder in grid
    And I set "2" Quantity for first backend order line item
    Then I should see next rows in "All Promotions" table
      | Code   | Promotion                    | Type      | Status | Discount |
      | test-1 | Line Item Discount Promotion | Line Item | Active | -$2.00   |
    And see next subtotals for "Backend Order":
      | Subtotal | Amount |
      | Subtotal | $10.00 |
      | Discount | -$2.00 |
      | Total    | $8.00  |
    When I save and close form
    Then I should see next rows in "All Promotions" table
      | Code   | Promotion                    | Type      | Status | Discount |
      | test-1 | Line Item Discount Promotion | Line Item | Active | -$2.00   |
    And see next subtotals for "Backend Order":
      | Subtotal | Amount |
      | Subtotal | $10.00 |
      | Discount | -$2.00 |
      | Total    | $8.00  |
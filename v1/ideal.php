<?php 

/*
	Copyright (C) 2012 Thijs van Dijk
	
	This file is part of dibs.

	dibs is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	dibs is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with dibs.  If not, see <http://www.gnu.org/licenses/>.
*/

/*
	Handle iDEAL payments
*/


require_once( "lib/cart.inc" );
require_once( "lib/item.inc" );
require_once( "lib/invoice.inc" );
require_once( "lib/payment/sisow-ideal.inc" );


function handle_ideal_request( $uri, &$output )
{
	$verb = $_SERVER["REQUEST_METHOD"];
	
	
	/**
	 * GET .../1/ideal/pay/{cart-id}
	 *
	 * Initiate an iDEAL payment to pay for the contents of this cart
	 **/
	if ( preg_match( '#^ideal/pay/([0-9]+)/([-0-9a-zA-z]+)/?$#', $uri, $A ) )
	{
		$bank_id = (int)$A[1];
		$cart_id = $A[2];
		if ( !Cart::exists( $cart_id ) ) return 3;
		if ( Cart::is_open( $cart_id ) ) return 10;
		$td = Cart::total_amount( $cart_id );
		if ( $td < 0.005 ) return 11;
		
		// Filter test bank
		global $enable_test_bank;
		if ( ! $enable_test_bank && $bank_id == 99 ) return 12;
		
		$inv = Invoice::assign( $cart_id );
		if ( ! $inv ) return 1;
		
		$url = SisowIdeal::initiate_payment( $cart_id, $bank_id, $inv );
		
		$output = array( "redirect-url" => $url );
		return 0;
	}
	
	
	/**
	 * GET .../1/ideal/{status}/{cart-id}
	 * 
	 * Return from iDEAL gateway
	 **/
	if ( preg_match( '#^ideal/(ok|cancel)/([-0-9a-zA-z]+)/?$#', $uri, $A ) )
	{
		$status = $A[1];
		$cart_id = $A[2];
		$unique_code = @$_REQUEST["ec"];
		
		$vfy = SisowIdeal::verify_payment( $cart_id, $unique_code );
		if ( $vfy["status"] == "success" )
		{
			$vfy["type"] = "ideal";
			db()->carts->update(
				array( "cart-id" => $cart_id ),
				array( '$push' => array( "payments", $vfy ) )
			);
			
			$td = Cart::total_amount( $cart_id );
			if ( $td - $vfy["amount"] < 0.005 )
				Cart::add_status( $cart_id, "paid" );
			
			$output = "Thank you very much.";
		}
		else
		{
			$output = array( "status" => $vfy["status"] );
		}
		return 0;
	}
	
	
	/**
	 * GET .../1/ideal/issuers
	 * 
	 * Get a list of all payment issuers
	 **/
	if ( preg_match( '#^ideal/issuers/?$#', $uri, $A ) )
	{
		$output = SisowIdeal::issuers();
		return 0;
	}
	
	
	
	print( "Invalid iDEAL operation" );
	return 2;
}


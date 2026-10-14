<?php
namespace App\Services;
class ServiceEmailTemplates {
 public static function all(): array {return [
  'support_update'=>['label'=>'Support case update','subject'=>'An update from Intuition Island Support','intro'=>'We have an update about your support request.'],
  'privacy_update'=>['label'=>'Privacy request update','subject'=>'Your Intuition Island privacy request','intro'=>'We are following up on your privacy request.'],
  'account_update'=>['label'=>'Account assistance','subject'=>'Your Intuition Island account','intro'=>'Our support team is contacting you about your account.'],
  'booking_update'=>['label'=>'Appointment assistance','subject'=>'Your Intuition Island appointment','intro'=>'We are following up about your appointment.'],
 ];}
 public static function render(string $key,string $name,string $details):array{
  $template=self::all()[$key];
  return ['subject'=>$template['subject'],'body'=>"Hello ".$name.",\n\n".$template['intro']."\n\n".$details."\n\nFor assistance, please use the Support inbox on Intuition Island. Never send your password or payment-card details.\n\nIntuition Island Support"];
 }
}

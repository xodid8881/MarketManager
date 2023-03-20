<?php
declare(strict_types=1);

namespace MarketManager\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\Player;
use MarketManager\MarketManager;

class OPCommand extends Command
{

  protected $plugin;

  public function __construct(MarketManager $plugin)
  {
    $this->plugin = $plugin;
    parent::__construct('시장관리', '시장관리 명령어.', '/시장관리');
  }

  public function execute(CommandSender $sender, string $commandLabel, array $args)
  {
    $name = $sender->getName ();
    $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
    $this->plugin->save ();
    $encode = [
      'type' => 'form',
      'title' => '[ MarketManager ]',
      'content' => '버튼을 눌러주세요.',
      'buttons' => [
        [
          'text' => '시장 관리'
        ],
        [
          'text' => '엔피시 소환'
        ]
      ]
    ];
    $packet = new ModalFormRequestPacket ();
    $packet->formId = 21378;
    $packet->formData = json_encode($encode);
    $sender->getNetworkSession()->sendDataPacket($packet);

  }
}

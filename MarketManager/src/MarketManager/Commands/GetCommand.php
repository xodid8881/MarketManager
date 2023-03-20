<?php
declare(strict_types=1);

namespace MarketManager\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\Player;
use MarketManager\MarketManager;

class GetCommand extends Command
{

  protected $plugin;
  private $chat;

  public function __construct(MarketManager $plugin)
  {
    $this->plugin = $plugin;
    parent::__construct('시장', '시장 명령어.', '/시장');
  }

  public function execute(CommandSender $sender, string $commandLabel, array $args)
  {
    $name = $sender->getName ();
    if (! isset ( $this->chat [$name] )) {
      $name = $sender->getName ();
      $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
      $this->plugin->save ();
      $this->plugin->MarketEvent($sender);
      $this->chat [$name] = date("YmdHis",strtotime ("+3 seconds"));
      return true;
    }
    if (date("YmdHis") - $this->chat [$name] < 3) {
      $sender->sendMessage ( $this->plugin->tag() . "이용 쿨타임이 지나지 않아 불가능합니다." );
      return true;
    } else {
      $name = $sender->getName ();
      $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
      $this->plugin->save ();
      $this->plugin->MarketEvent($sender);
      $this->chat [$name] = date("YmdHis",strtotime ("+3 seconds"));
      return true;
    }
  }
}

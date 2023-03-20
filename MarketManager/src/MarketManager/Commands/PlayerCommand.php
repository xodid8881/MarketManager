<?php
declare(strict_types=1);

namespace MarketManager\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\Player;
use MarketManager\MarketManager;

class PlayerCommand extends Command
{

  protected $plugin;

  public function __construct(MarketManager $plugin)
  {
    $this->plugin = $plugin;
    parent::__construct('시장등록', '시장등록 명령어.', '/시장등록');
  }

  public function execute(CommandSender $sender, string $commandLabel, array $args)
  {
    $name = $sender->getName ();
    $encode = [
      'type' => 'custom_form',
      'title' => '[ MarketManager ]',
      'content' => [
        [
          'type' => 'input',
          'text' => '물품 이름을 적어주세요.'
        ],
        [
          'type' => 'input',
          'text' => '물품 갯수를 적어주세요.'
        ],
        [
          'type' => 'input',
          'text' => "생각한 즉시 구매가를 적어주세요.\n터무니 없는 가격이면 신고 당할 수 있습니다."
        ],
        [
          'type' => 'input',
          'text' => "생각한 최소 경매가를 적어주세요.\n터무니 없는 가격이면 신고 당할 수 있습니다."
        ]
      ]
    ];
    $packet = new ModalFormRequestPacket ();
    $packet->formId = 21379;
    $packet->formData = json_encode($encode);
    $sender->getNetworkSession()->sendDataPacket($packet);
  }
}

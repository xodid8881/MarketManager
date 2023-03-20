<?php
declare(strict_types=1);

namespace MarketManager;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\tile\Chest;

use LifeInventoryLib\InventoryLib\InvLibManager;
use LifeInventoryLib\InventoryLib\LibInvType;
use LifeInventoryLib\InventoryLib\InvLibAction;
use LifeInventoryLib\InventoryLib\SimpleInventory;
use LifeInventoryLib\InventoryLib\LibInventory;

use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldManager;

use MoneyManager\MoneyManager;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\inventory\ContainerInventory;

use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use WarningManager\WarningManager;

use pocketmine\permission\DefaultPermissions;

class EventListener implements Listener
{

  protected $plugin;
  private $chat;

  public function __construct(MarketManager $plugin)
  {
    $this->plugin = $plugin;
  }
  public function OnJoin (PlayerJoinEvent $event)
  {
    $player = $event->getPlayer ();
    $name = $player->getName ();
    if (!isset($this->plugin->pldb [strtolower($name)])){
      $this->plugin->pldb [strtolower($name)] ["등록리스트"] = [];
      $this->plugin->pldb [strtolower($name)] ["구매도우미"] = "없음";
      $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "없음";
      $this->plugin->pldb [strtolower($name)] ["차단기록"] = "없음";
      $this->plugin->pldb [strtolower($name)] ["차단종료기간"] = "없음";
      $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
      $this->plugin->save ();
    }
  }
  public function onTransaction(InventoryTransactionEvent $event) {
    $transaction = $event->getTransaction();
    $player = $transaction->getSource ();
    $name = $player->getName ();
    foreach($transaction->getActions() as $action){
      if($action instanceof SlotChangeAction){
        $inv = $action->getInventory();
        if($inv instanceof LibInventory){
          $slot = $action->getSlot ();
          $item = $inv->getItem ($slot);
          $id = $item->getId ();
          $damage = $item->getMeta ();
          $itemname = $item->getCustomName ();
          $nbt = $item->jsonSerialize ();
          if ($inv->getTitle() == '[ MarketManager ] 시장도우미'){
            if ( $itemname == "시장입장" ) {
              $event->cancel ();
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "없음";
              $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
              $this->plugin->save ();
              $this->plugin->MarketEvent ($player);
              return true;
            }
            if ( $itemname == "나의 시장확인" ) {
              $event->cancel ();
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "없음";
              $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
              $this->plugin->save ();
              $this->plugin->BestOPBookEvent ($player);
              return true;
            }
            if ( $itemname == "나가기" ) {
              $event->cancel ();
              $inv->onClose($player);
              return true;
            }
          }
          if ($inv->getTitle() == '[ MarketManager ] 시장관리'){
            if ( $itemname == "신고관리" ) {
              $event->cancel ();
              if ($this->plugin->Warningdb ["WarningCheck"] == "no") {
                $player->sendMessage($this->plugin->tag() . "경고부분은 이용이 불가능합니다.");
                $inv->onClose($player);
                return true;
              }
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "없음";
              $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
              $this->plugin->save ();
              $this->plugin->MarketWareEvent ($player);
              return true;
            }
            if ( $itemname == "물품관리" ) {
              $event->cancel ();
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "없음";
              $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
              $this->plugin->save ();
              $this->plugin->MarketItemCheckEvent ($player);
              return true;
            }
            if ( $itemname == "나가기" ) {
              $event->cancel ();
              $inv->onClose($player);
              return true;
            }
          }
        
          if ($inv->getTitle() == '[ MarketManager ] 시장터'){
            if (isset($this->plugin->marketdb ["물품리스트"] [$itemname])) {
              $event->cancel ();
              if ($this->plugin->pldb [strtolower($name)] ["이용이벤트"] == "구매모드") {
                $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = $itemname;
                $this->plugin->save ();
                $inv->onClose($player);
                $this->plugin->SayTaskEvent ($player);
                return true;
              } else if ($this->plugin->pldb [strtolower($name)] ["이용이벤트"] == "신고모드") {
                $event->cancel ();
                $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = $itemname;
                $this->plugin->save ();
                $inv->onClose($player);
                $this->plugin->WareTaskEvent ($player);
                return true;
              } else {
                $event->cancel ();
                $inv->onClose($player);
                return true;
              }
            }
            if ( $itemname == "구매모드" ) {
              $event->cancel ();
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "구매모드";
              $this->plugin->save ();
              return true;
            }
            if ( $itemname == "신고모드" ) {
              if ($this->plugin->Warningdb ["WarningCheck"] == "no") {
                $event->cancel ();
                $player->sendMessage($this->plugin->tag() . "경고부분은 이용이 불가능합니다.");
                $inv->onClose($player);
                return true;
              }
              $event->cancel ();
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "신고모드";
              $this->plugin->save ();
              return true;
            }
            if ( $itemname == "이전페이지" ) {
              $event->cancel ();
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["페이지"] -= 1;
              $this->plugin->save ();
              $this->plugin->MarketEvent ($player);
              return true;
            }
            if ( $itemname == "다음페이지" ) {
              $event->cancel ();
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["페이지"] += 1;
              $this->plugin->save ();
              $this->plugin->MarketEvent ($player);
              return true;
            }
            if ( $itemname == "나가기" ) {
              $event->cancel ();
              $inv->onClose($player);
              return true;
            }
            $event->cancel ();
          }
          if ($inv->getTitle() == '[ MarketManager ] 시장터관리장'){
            if (isset($this->plugin->marketdb ["물품리스트"] [$itemname])) {
              if ($this->plugin->pldb [strtolower($name)] ["이용이벤트"] == "제거모드") {
                $event->cancel ();
                $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = $itemname;
                $this->plugin->save ();
                $this->plugin->RemoveTaskEvent ($player);
                $inv->onClose($player);
              }
            }
            if ( $itemname == "제거모드" ) {
              $event->cancel ();
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "제거모드";
              $this->plugin->save ();
              return true;
            }
            if ( $itemname == "이전페이지" ) {
              $event->cancel ();
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["페이지"] -= 1;
              $this->plugin->save ();
              $this->plugin->MarketItemCheckEvent ($player);
              return true;
            }
            if ( $itemname == "다음페이지" ) {
              $event->cancel ();
              $inv->onClose($player);
              $this->plugin->pldb [strtolower($name)] ["페이지"] += 1;
              $this->plugin->save ();
              $this->plugin->MarketItemCheckEvent ($player);
              return true;
            }
            if ( $itemname == "나가기" ) {
              $event->cancel ();
              $inv->onClose($player);
              return true;
            }
            $event->cancel ();
          }
          if ($inv->getTitle() == '[ MarketManager ] 나의 등록물품함'){
            if (isset($this->plugin->pldb [strtolower($name)] ["등록리스트"] [$itemname])) {
              $event->cancel ();
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = $itemname;
              $this->plugin->save ();
              $inv->onClose($player);
              $this->plugin->MyTaskEvent ($player);
              return true;
            }
            if ( $itemname == "나가기" ) {
              $event->cancel ();
              $inv->onClose($player);
              return true;
            }
            $event->cancel ();
          }
          if ($inv->getTitle() == '[ MarketManager ] 신고관리'){
            if (isset($this->plugin->marketdb ["물품리스트"] [$itemname])) {
              $event->cancel ();
              $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = $itemname;
              $this->plugin->save ();
              $this->plugin->OpWareTaskEvent ($player);
              $inv->onClose($player);
              return true;
            }
            if ( $itemname == "나가기" ) {
              $event->cancel ();
              $inv->onClose($player);
              return true;
            }
            $event->cancel ();
          }
        }
      }
    }
  }

  public function onPacket(DataPacketReceiveEvent $event)
  {
    $packet = $event->getPacket();
    $player = $event->getOrigin()->getPlayer();
    if ($packet instanceof ModalFormResponsePacket) {
      $name = $player->getName();
      $id = $packet->formId;
      if($packet->formData == null) {
        return true;
      }
      $data = json_decode($packet->formData, true);
      if ($id === 21378) {
        if ($data === 0) {
          if (!$player->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
            $player->sendMessage($this->plugin->tag() . "권한이 없습니다.");
            return true;
          }
          $this->plugin->onOpOpen ($player);
          return true;
        }
        if ($data === 1) {
          if (!$player->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
            $player->sendMessage($this->plugin->tag() . "권한이 없습니다.");
            return true;
          }
          $this->plugin->ShopEntitySpawn ($player);
          $player->sendMessage( $this->plugin->tag() . '시장 엔피시를 소환했습니다.');
          return true;
        }
      }
      if ($id === 21379) {
        if (!isset($data[0])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        if (!isset($data[1])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        if (!isset($data[2])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        if (!isset($data[3])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        if (! is_numeric ($data[1])) {
          $player->sendMessage ( $this->plugin->tag() . "숫자를 이용 해야됩니다. " );
          return true;
        }
        if (! is_numeric ($data[2])) {
          $player->sendMessage ( $this->plugin->tag() . "숫자를 이용 해야됩니다. " );
          return true;
        }
        if (! is_numeric ($data[3])) {
          $player->sendMessage ( $this->plugin->tag() . "숫자를 이용 해야됩니다. " );
          return true;
        }
        if (isset($this->plugin->marketdb ["물품리스트"] [$data[0]])) {
          $player->sendMessage ( $this->plugin->tag() . "해당 이름으로 이미 시장에 등록된 물품이 있습니다." );
          return true;
        }
        if ($data[2] <= $data[3]) {
          $player->sendMessage ( $this->plugin->tag() . "경매가는 즉시 구매가보다 높거나 같으면 안됩니다." );
          return true;
        }
        $HandItem = $player->getInventory()->getItemInHand();
        if ($HandItem->getId () == 0) {
          $player->sendMessage ( $this->plugin->tag() . "시장에 등록할 아이템을 들고 이용해주세요. " );
          return true;
        }
        $itemnbt = $HandItem->jsonSerialize ();
        $item = Item::jsonDeserialize($itemnbt);

        if ($player->getInventory ()->contains ( $item->setCount ((int)$data[1]) )) {
          $player->getInventory ()->removeItem ( $item->setCount ((int)$data[1]) );
        } else {
          $player->sendMessage ( $this->plugin->tag() . "보유한 아이템 갯수보다 많이 등록할 수 없습니다." );
          return true;
        }
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["물품이름"] = $data[0];
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["등록자이름"] = $name;
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ['nbt'] = $item->jsonSerialize();
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["갯수"] = $data[1];
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["즉시구매가"] = $data[2];
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["최소경매가"] = $data[3];
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["현재경매가"] = $data[3];
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["경매자"] = "없음";
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["등록당시시간"] = date("YmdHis");
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["등록종료시간"] = date("YmdHis",strtotime ("+10 minutes"));
        $this->plugin->marketdb ["물품리스트"] [$data[0]] ["종료시간"] = date("Y년 m월 d일 H시 i분 s초",strtotime ("+10 minutes"));

        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["물품이름"] = $data[0];
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["등록자이름"] = $name;
        $this->plugin->pldb [strtolower($name)] ["물품리스트"] [$data[0]] ['nbt'] = $item->jsonSerialize();
        $this->plugin->pldb [strtolower($name)] ["물품리스트"] [$data[0]] ["갯수"] = $data[1];
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["즉시구매가"] = $data[2];
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["최소경매가"] = $data[3];
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["현재경매가"] = $data[3];
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["경매자"] = "없음";
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["등록당시시간"] = date("YmdHis");
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["등록종료시간"] = date("YmdHis",strtotime ("+10 minutes"));
        $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$data[0]] ["종료시간"] = date("Y년 m월 d일 H시 i분 s초",strtotime ("+10 minutes"));
        $this->plugin->save ();
        $this->plugin->getServer()->broadcastMessage( $this->plugin->tag() . "{$name} 님이 {$data[0]} 이름으로 시장에 아이템을 올렸습니다.");
        $player->sendMessage ( $this->plugin->tag() . "손에든 아이템을 시장에 등록했습니다." );
        return true;
      }
      if ($id === 21380) {
        if (!isset($data[1])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        switch($data[0]){
          case 0 :
          if ($data[1] != "YES"){
            $player->sendMessage( $this->plugin->tag() . '즉시 구매를 원하시면 이용이벤트 선택 후 YES 를 적어주세요.');
            return;
          }
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];
          $count = $this->plugin->marketdb ["물품리스트"] [$Market] ["갯수"];
          $livemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
          $playername = $this->plugin->marketdb ["물품리스트"] [$Market] ["등록자이름"];

          $item = Item::jsonDeserialize($this->plugin->marketdb['물품리스트'][$Market]['nbt']);

          if (MoneyManager::getInstance ()->getMoney ($name) >= $livemoney){
            MoneyManager::getInstance ()->sellMoney ($name,$livemoney);
            MoneyManager::getInstance ()->addMoney ($playername,$livemoney);
            $this->plugin->GiveItem ($name,$marketname,$item->jsonSerialize ());
            unset($this->plugin->pldb [strtolower($playername)] ["등록리스트"] [$Market]);
            unset($this->plugin->marketdb ["물품리스트"] [$Market]);
            $player->sendMessage ( $this->plugin->tag() . "{$Market} 이름의 물품을 {$livemoney} 원을 주고 구매했습니다.");
            return true;
          } else {
            $player->sendMessage ( $this->plugin->tag() . "구매하기에 보유한 돈이 부족합니다.");
            return true;
          }
          break;
          case 1 :
          if (! is_numeric ($data[1])) {
            $player->sendMessage ( $this->plugin->tag() . "숫자를 이용 해야됩니다. " );
            return true;
          }
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];
          $count = $this->plugin->marketdb ["물품리스트"] [$Market] ["갯수"];
          $livemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
          $timemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["최소경매가"];
          $livetimemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["현재경매가"];
          $playername = $this->plugin->marketdb ["물품리스트"] [$Market] ["등록자이름"];

          $item = Item::jsonDeserialize($this->plugin->marketdb['물품리스트'][$Market]['nbt']);

          if ($livemoney <= $data[1]){
            MoneyManager::getInstance ()->sellMoney ($name,$livemoney);
            MoneyManager::getInstance ()->addMoney ($playername,$livemoney);
            $this->plugin->GiveItem ($name,$marketname,$item->jsonSerialize ());
            unset($this->plugin->pldb [strtolower($playername)] ["등록리스트"] [$Market]);
            unset($this->plugin->marketdb ["물품리스트"] [$Market]);
            $player->sendMessage ( $this->plugin->tag() . "적으신 경매가가 즉시 구매가와 같아 거래가 채결됬습니다.");
            $player->sendMessage ( $this->plugin->tag() . "당신은 {$Market} 이름의 물품을 즉시 구매가로 {$livemoney} 원을 주고 구매했습니다.");
            return true;
          }
          if ($livetimemoney >= $data[1]){
            $player->sendMessage ( $this->plugin->tag() . "현재 경매가보다 낮습니다. 현재 경매가 => {$livetimemoney} 원");
            return true;
          }
          if (MoneyManager::getInstance ()->getMoney ($name) >= $data[1]){
            $this->plugin->marketdb ["물품리스트"] [$Market] ["경매자"] = $name;
            $this->plugin->marketdb ["물품리스트"] [$Market] ["현재경매가"] = $data[1];
            $this->plugin->save ();
          } else {
            $player->sendMessage ( $this->plugin->tag() . "경매 신청한 금액보다 보유한 돈이 부족합니다.");
            return true;
          }
          break;
        }
      }
      if ($id === 21381) {
        if (!isset($data[1])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        switch($data[0]){
          case 0 :

          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];
          $count = $this->plugin->marketdb ["물품리스트"] [$Market] ["갯수"];
          $livemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
          $timemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["최소경매가"];
          $livetimemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["현재경매가"];
          $playername = $this->plugin->marketdb ["물품리스트"] [$Market] ["등록자이름"];

          $this->plugin->marketdb ["신고물품"] [$Market] ["물품이름"] = $marketname;
          $this->plugin->marketdb ["신고물품"] [$Market] ["등록자이름"] = $playername;
          $this->plugin->marketdb ["신고물품"] [$Market] ["신고자"] = $name;
          $this->plugin->marketdb ["신고물품"] [$Market] ['id'] = $this->plugin->marketdb ["물품리스트"] [$Market] ['id'];
          $this->plugin->marketdb ["신고물품"] [$Market] ['dmg'] = $this->plugin->marketdb ["물품리스트"] [$Market] ['id'];
          $this->plugin->marketdb ["신고물품"] [$Market] ['nbt'] = $this->plugin->marketdb ["물품리스트"] [$Market] ['id'];
          $this->plugin->marketdb ["신고물품"] [$Market] ["갯수"] = $count;
          $this->plugin->marketdb ["신고물품"] [$Market] ["즉시구매가"] = $livemoney;
          $this->plugin->marketdb ["신고물품"] [$Market] ["최소경매가"] = $timemoney;
          $this->plugin->marketdb ["신고물품"] [$Market] ["현재경매가"] = $livetimemoney;
          $this->plugin->marketdb ["신고물품"] [$Market] ["신고위치"] = "금액";
          $this->plugin->marketdb ["신고물품"] [$Market] ["신고글"] = $data[1];
          $this->plugin->save ();
          break;
          case 1 :
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];
          $count = $this->plugin->marketdb ["물품리스트"] [$Market] ["갯수"];
          $livemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
          $timemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["최소경매가"];
          $livetimemoney = $this->plugin->marketdb ["물품리스트"] [$Market] ["현재경매가"];
          $playername = $this->plugin->marketdb ["물품리스트"] [$Market] ["등록자이름"];

          $this->plugin->marketdb ["신고물품"] [$Market] ["물품이름"] = $marketname;
          $this->plugin->marketdb ["신고물품"] [$Market] ["등록자이름"] = $playername;
          $this->plugin->marketdb ["신고물품"] [$Market] ["신고자"] = $name;
          $this->plugin->marketdb ["신고물품"] [$Market] ['id'] = $this->plugin->marketdb ["물품리스트"] [$Market] ['id'];
          $this->plugin->marketdb ["신고물품"] [$Market] ['dmg'] = $this->plugin->marketdb ["물품리스트"] [$Market] ['id'];
          $this->plugin->marketdb ["신고물품"] [$Market] ['nbt'] = $this->plugin->marketdb ["물품리스트"] [$Market] ['id'];
          $this->plugin->marketdb ["신고물품"] [$Market] ["갯수"] = $count;
          $this->plugin->marketdb ["신고물품"] [$Market] ["즉시구매가"] = $livemoney;
          $this->plugin->marketdb ["신고물품"] [$Market] ["최소경매가"] = $timemoney;
          $this->plugin->marketdb ["신고물품"] [$Market] ["현재경매가"] = $livetimemoney;
          $this->plugin->marketdb ["신고물품"] [$Market] ["신고위치"] = "아이템";
          $this->plugin->marketdb ["신고물품"] [$Market] ["신고글"] = $data[1];
          $this->plugin->save ();
          break;
        }
      }
      if ($id === 21382) {
        if (!isset($data[1])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        switch($data[0]){
          case 0 :
          if (! is_numeric ($data[0])) {
            $player->sendMessage ( $this->plugin->tag() . "숫자를 이용 해야됩니다. " );
            return;
          }
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $playername = $this->plugin->marketdb ["신고물품"] [$Market] ["등록자이름"];
          WarningManager::getInstance ()->addWarningPoint ($playername,$data[0],"시장 물품경고");
          $this->plugin->pldb [strtolower($playername)] ["차단기록"] = date("YmdHis",strtotime ("+20 minutes"));
          $this->plugin->pldb [strtolower($playername)] ["차단종료기간"] = date("Y년 m월 d일 H시 i분 s초",strtotime ("+20 minutes"));
          unset ($this->plugin->marketdb ["신고물품"] [$Market] ["물품이름"]);
          $player->sendMessage ( $this->plugin->tag() . "신고를 체결하고 등록자에게 {$data[0]} 개의 경고를 부여했습니다." );
          if (isset($this->plugin->marketdb ["물품리스트"] [$Market])) {
            $item = Item::jsonDeserialize($this->plugin->marketdb['신고물품'][$Market]['nbt']);
            $this->plugin->BackGiveItem ($playername,$Market,$item->jsonSerialize ());
            unset ($this->plugin->marketdb ["물품리스트"] [$Market]);
          }
          if (isset($this->plugin->pldb [strtolower($playername)] ["등록리스트"] [$Market])){
            unset($this->plugin->pldb [strtolower($playername)] ["등록리스트"] [$Market]);
          }
          break;
          case 1 :
          if ($data[0] != "YES") {
            $player->sendMessage ( $this->plugin->tag() . "YES 를 정확하게 입력해주세요." );
            return;
          }
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          unset ($this->plugin->marketdb ["신고물품"] [$Market] ["물품이름"]);
          $this->plugin->save ();
          $player->sendMessage ( $this->plugin->tag() . "해당 신고를 무효화 시켰습니다." );
          break;
        }
      }
      if ($id === 21383) {
        if ($data === 0) {
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];
          $item = Item::jsonDeserialize($this->plugin->marketdb['물품리스트'][$Market]['nbt']);
          $upname = $this->plugin->marketdb ["물품리스트"] [$Market] ["등록자이름"];

          $this->plugin->GiveItem ($upname,$marketname,$item->jsonSerialize ());

          unset($this->plugin->pldb [strtolower($upname)] ["등록리스트"] [$Market]);
          unset($this->plugin->marketdb ["물품리스트"] [$Market]);
          $this->plugin->save ();
          $player->sendMessage ( $this->plugin->tag() . "{$Market} 이름인 물품의 등록을 취소했습니다.");
          return true;
        }
        if ($data === 1) {
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];

          $this->plugin->marketdb ["물품리스트"] [$Market] ["등록종료시간"] = date("YmdHis",strtotime ("+10 minutes"));
          $this->plugin->marketdb ["물품리스트"] [$Market] ["종료시간"] = date("Y년 m월 d일 H시 i분 s초",strtotime ("+10 minutes"));
          $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$Market] ["등록종료시간"] = date("YmdHis",strtotime ("+10 minutes"));
          $this->plugin->pldb [strtolower($name)] ["등록리스트"] [$Market] ["종료시간"] = date("Y년 m월 d일 H시 i분 s초",strtotime ("+10 minutes"));
          $this->plugin->save ();

          $player->sendMessage ( $this->plugin->tag() . "{$Market} 이름의 물품의 등록기간을 연장했습니다.");
          return true;
        }
      }
      if ($id === 21384) {
        if (!isset($data[1])) {
          $player->sendMessage( $this->plugin->tag() . '빈칸을 채워주세요.');
          return;
        }
        if ("YES" != $data[1]) {
          $player->sendMessage( $this->plugin->tag() . 'YES 를 정확하게 적어주세요.');
          return;
        }
        switch($data[0]){
          case 0 :
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];
          $item = Item::jsonDeserialize($this->plugin->marketdb['물품리스트'][$Market]['nbt']);
          $upname = $this->plugin->marketdb ["물품리스트"] [$Market] ["등록자이름"];

          $this->plugin->GiveItem ($upname,$marketname,$item->jsonSerialize ());

          unset($this->plugin->pldb [strtolower($upname)] ["등록리스트"] [$Market]);
          unset($this->plugin->marketdb ["물품리스트"] [$Market]);
          $this->plugin->save ();
          $player->sendMessage ( $this->plugin->tag() . "{$Market} 이름인 물품을 제거했습니다.");
          break;
          case 1 :
          $Market = $this->plugin->pldb [strtolower($name)] ["이용이벤트"];
          $marketname = $this->plugin->marketdb ["물품리스트"] [$Market] ["물품이름"];
          $upname = $this->plugin->marketdb ["물품리스트"] [$Market] ["등록자이름"];

          unset($this->plugin->pldb [strtolower($upname)] ["등록리스트"] [$Market]);
          unset($this->plugin->marketdb ["물품리스트"] [$Market]);
          $this->plugin->save ();
          $player->sendMessage ( $this->plugin->tag() . "{$Market} 이름인 물품을 제거했습니다.");
          break;
        }
      }
    } else if($packet instanceof InventoryTransactionPacket){
      $trData = $packet->trData;
      if($trData instanceof UseItemOnEntityTransactionData){
        $name = $player->getName();
        $pos = $player->getPosition();
        $entity = $pos->getWorld()->getEntity($trData->getActorRuntimeId());
        if (is_null($entity)) return;
        if (is_null($entity->getNameTag())) return;
        if ($entity->getNameTag() == "시장도우미"){
          if ($player->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
            $item = $player->getInventory ()->getItemInHand ();
            $id = $item->getId ();
            if ($id == 54){
              $entity->close ();
              return true;
            }
          }
          if (! isset ( $this->chat [$name] )) {
            $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "구매";
            $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
            $this->plugin->save ();
            $this->plugin->MarketEvent ($player);
            $this->chat [$name] = date("YmdHis",strtotime ("+3 seconds"));
            return true;
          }
          if (date("YmdHis") - $this->chat [$name] < 3) {
            $player->sendMessage ( $this->plugin->tag() . "이용 쿨타임이 지나지 않아 불가능합니다." );
            return true;
          } else {
            $this->plugin->pldb [strtolower($name)] ["이용이벤트"] = "구매";
            $this->plugin->pldb [strtolower($name)] ["페이지"] = 1;
            $this->plugin->save ();
            $this->plugin->MarketEvent ($player);
            $this->chat [$name] = date("YmdHis",strtotime ("+3 seconds"));
            return true;
          }
        }
      }
    }
  }
  public function onEntityDamage(EntityDamageByEntityEvent $event) {
    $entity = $event->getEntity ();
    $damager = $event->getDamager ();
    if (! $damager instanceof Player) {
      if ($entity->getNameTag() != null){
        if ($entity->getNameTag() == "시장도우미"){
          $event->cancel();
        }
      }
    }
    if ($damager instanceof Player) {
      if ($entity->getNameTag() != null){
        if ($entity->getNameTag() == "시장도우미"){
          $event->cancel();
          return true;
        }
      }
    }
  }
  public function onDeath(EntityDeathEvent $event){
    $entity = $event->getEntity();
    if ($entity->getNameTag() != null){
      if ($entity->getNameTag() == "시장도우미"){
        $event->setDrops([]);
      }
    }
  }
}

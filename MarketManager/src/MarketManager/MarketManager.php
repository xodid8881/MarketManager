<?php
declare(strict_types=1);

namespace MarketManager;

use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\network\mcpe\protocol\OnScreenTextureAnimationPacket;
use pocketmine\utils\Config;
use pocketmine\scheduler\Task;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;

use MarketManager\Commands\GetCommand;
use MarketManager\Commands\OPCommand;
use MarketManager\Commands\PlayerCommand;

use LifeInventoryLib\LifeInventoryLib;
use LifeInventoryLib\InventoryLib\LibInvType;

use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\Human;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Entity;
use MoneyManager\MoneyManager;
use MailboxAPI\MailboxAPI;
// monster
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldManager;

use WarningManager\WarningManager;

class MarketManager extends PluginBase
{
  protected $config;
  public $db;
  public $get = [];
  private static $instance = null;

  public static function getInstance(): MarketManager
  {
    return static::$instance;
  }

  public function onLoad():void
  {
    self::$instance = $this;
  }

  public function onEnable():void
  {
    $this->player = new Config ($this->getDataFolder() . "players.yml", Config::YAML);
    $this->pldb = $this->player->getAll();
    $this->market = new Config ($this->getDataFolder() . "markets.yml", Config::YAML);
    $this->marketdb = $this->market->getAll();
    $this->allmarket = new Config ($this->getDataFolder() . "allmarkets.yml", Config::YAML);
    $this->allmarketdb = $this->allmarket->getAll();
    $this->Warning = new Config ($this->getDataFolder() . "WarningCheck.yml", Config::YAML, [
      "WarningCheck" => "ok",
      "사용법" => "경고 플러그인 이용시 ok 아닐시 no\n서버를 완전히 종료후 작업해주세요."
      ] );
      $this->Warningdb = $this->Warning->getAll();
      if ($this->getServer ()->getPluginManager ()->getPlugin ( "WarningManager" ) == null) {
        if ($this->Warningdb ["WarningCheck"] == "ok") {
          $this->getServer ()->getLogger ()->warning ( "WarningManager 플러그인이 없기에 비활성화 됩니다." );
          $this->getServer ()->getPluginManager ()->disablePlugin ( $this );
        } else if ($this->Warningdb ["WarningCheck"] == false) {
          $this->getServer ()->getLogger ()->warning ( "WarningManager 플러그인이 없이 플러그인을 이용합니다." );
        } else {
          $this->getServer ()->getLogger ()->warning ( "WarningManager 플러그인이 인식부분 오류로 플러그인이 비활성화 됩니다." );
          $this->getServer ()->getLogger ()->warning ( "플러그인 체크 콘피그를 제대로 작성해주세요." );
          $this->getServer ()->getPluginManager ()->disablePlugin ( $this );
        }
      } else {
        if ($this->Warningdb ["WarningCheck"] == "ok") {
          $this->getServer ()->getLogger ()->warning ( "WarningManager 플러그인과 연동되어 구동됩니다." );
        } else if ($this->Warningdb ["WarningCheck"] == false) {
          $this->getServer ()->getLogger ()->warning ( "WarningManager 플러그인이 존재합니다.\n경고기능을 이용하시려면 플러그인 체크 콘피그를 수정해주세요." );
        } else {
          $this->getServer ()->getLogger ()->warning ( "WarningManager 플러그인이 인식부분 오류로 플러그인이 비활성화 됩니다." );
          $this->getServer ()->getLogger ()->warning ( "플러그인 체크 콘피그를 제대로 작성해주세요." );
          $this->getServer ()->getPluginManager ()->disablePlugin ( $this );
        }
      }
      $this->getServer()->getCommandMap()->register('MarketManager', new GetCommand($this));
      $this->getServer()->getCommandMap()->register('MarketManager', new OPCommand($this));
      $this->getServer()->getCommandMap()->register('MarketManager', new PlayerCommand($this));
      $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
      $this->getScheduler ()->scheduleRepeatingTask ( new PlayerMarketTask ( $this, $this->player ), 20 );
    }

    public function tag() : string
    {
      return "§l§b[시장]§r§7 ";
    }

    public function ShopEntitySpawn($player){
      $pos = $player->getPosition();
      $loc = $player->getLocation();
      $loc = new Location($pos->getFloorX() + 0.5, $pos->getFloorY() + 0.05, $pos->getFloorZ() + 0.5,
      $pos->getWorld(), $loc->getYaw(), $loc->getPitch());
      $npc = new Human($loc, $player->getSkin());
      $npc->setNameTag("시장도우미");
      $npc->setNameTagAlwaysVisible();
      $npc->spawnToAll();
      return true;
    }

    public function onOpen($player) {
      $name = $player->getName ();
      $playerPos = $player->getPosition();
      $inv = LifeInventoryLib::getInstance ()->create("HOPPER", new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), '[ MarketManager ] 시장도우미',$player);
      $inv->setItem( 1 , ItemFactory::getInstance()->get(386, 0, 1)->setCustomName("시장입장")->setLore([ "시장에 입장합니다.\n인벤토리로 가져가보세요." ]) );
      $inv->setItem( 2 , ItemFactory::getInstance()->get(426, 0, 1)->setCustomName("나가기")->setLore([ "시장 도우미 GUI에서 나갑니다.\n인벤토리로 가져가보세요." ]));
      $inv->setItem( 3 , ItemFactory::getInstance()->get(54, 0, 1)->setCustomName("나의 시장확인")->setLore([ "나의 시장을 관리합니다.\n인벤토리로 가져가보세요." ]));
      LifeInventoryLib::getInstance ()->send($inv, $player);
      return true;
    }

    public function SayTaskEvent ($player) {
      $this->getScheduler()->scheduleDelayedTask(new class ($this, $player) extends Task {
        protected $owner;
        public function __construct(MarketManager $owner,Player $player) {
          $this->owner = $owner;
          $this->player = $player;
        }
        public function onRun():void
        {
          $this->owner->SayManagerUI($this->player);
        }
      }, 20);
    }
    public function SayManagerUI(Player $player)
    {
      $name = $player->getName ();

      $Market = $this->pldb [strtolower($name)] ["이용이벤트"];
      $marketname = $this->marketdb ["물품리스트"] [$Market] ["물품이름"];
      $count = $this->marketdb ["물품리스트"] [$Market] ["갯수"];
      $livemoney = $this->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
      $timemoney = $this->marketdb ["물품리스트"] [$Market] ["최소경매가"];
      $livetimemoney = $this->marketdb ["물품리스트"] [$Market] ["현재경매가"];
      $outtime = $this->marketdb ["물품리스트"] [$Market] ["종료시간"];
      $playername = $this->marketdb ["물품리스트"] [$Market] ["등록자이름"];
      $encode = [
        'type' => 'custom_form',
        'title' => '[ MarketManager ] | 구매',
        'content' => [
          [
            'type' => 'dropdown',
            'text' => '이용 방법',
            "options" => ["즉시구매","경매참여"]
          ],
          [
            'type' => 'input',
            'text' => "§6-----------------------------\n\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n§6만료일 §f=> §6{$outtime}\n\n§6-----------------------------\n§6즉시구매§f를 이용하신다면 대문자 §6YES §f를 적어주세요.\n§6경매§f를 이용하신다면 경매가 보다 높게 금액을 적어주세요.\n§6-----------------------------"
          ]
        ]
      ];
      $packet = new ModalFormRequestPacket ();
      $packet->formId = 21380;
      $packet->formData = json_encode($encode);
      $player->getNetworkSession()->sendDataPacket($packet);
      return true;
    }

    public function MarketEvent($player) {
      $name = $player->getName ();
      $livetime = (int)date("YmdHis");
      if (isset($this->pldb [strtolower($name)] ["차단기록"])) {
        if (is_numeric ($this->pldb [strtolower($name)] ["차단기록"])) {
          $time = (int)$this->pldb [strtolower($name)] ["차단기록"];
          $stoptime = $this->pldb [strtolower($playername)] ["차단종료기간"];
          if ($livetime < $time) {
            $player->sendMessage ($this->tag() . "당신은 경고로 인해 상점이용에 제한이 있습니다.\n제한이 풀리는 기간 => {$stoptime}");
            return true;
          }
        }
      }

      $playerPos = $player->getPosition();
      $inv = LifeInventoryLib::getInstance ()->create("DOUBLE_CHEST", new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), '[ MarketManager ] 시장터',$player);
      $arr = [];
      $i = 0;
      $page = 1;
      unset($this->allmarketdb ["물품리스트"]);
      $this->save ();
      if (isset($this->marketdb ["물품리스트"])){
        foreach($this->marketdb ["물품리스트"] as $MarketItem => $v){
          if ( $i <= 48) {
            $this->allmarketdb ["물품리스트"] [$page] [$i] = $MarketItem;
            $this->save ();
          } else {
            ++$page;
            $pageData = (int)$page-1;
            $getpage = (int)$page*49;
            $iData = $page-$getpage;
            $this->allmarketdb ["물품리스트"] [$page] [$iData] = $MarketItem;
            $this->save ();
          }
          ++$i;
        }
        $playerpage = $this->pldb [strtolower($name)] ["페이지"];
        if (isset($this->allmarketdb ["물품리스트"] [$playerpage])) {
          foreach($this->allmarketdb ["물품리스트"] [$playerpage] as $is => $v){

            $Market = $this->allmarketdb ["물품리스트"] [$playerpage] [$is];
            if (isset($this->marketdb ["물품리스트"] [$Market])) {
              $marketname = $this->marketdb ["물품리스트"] [$Market] ["물품이름"];
              $count = $this->marketdb ["물품리스트"] [$Market] ["갯수"];
              $livemoney = $this->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
              $timemoney = $this->marketdb ["물품리스트"] [$Market] ["최소경매가"];
              $livetimemoney = $this->marketdb ["물품리스트"] [$Market] ["현재경매가"];
              $outtime = $this->marketdb ["물품리스트"] [$Market] ["종료시간"];
              $playername = $this->marketdb ["물품리스트"] [$Market] ["등록자이름"];
              $item = Item::jsonDeserialize($this->marketdb['물품리스트'][$Market]['nbt']);
              $item->setCustomName ($marketname);
              $lore = $item->getLore();
              $lore [] = "\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n§6만료일 §f=> §6{$outtime}\n\n§6인벤토리에 가져오면 구매도우미가 열립니다.";
              $item->setLore ($lore);
              $inv->setItem( $is , $item );
            } else {
              unset($this->allmarketdb ["물품리스트"] [$playerpage] [$is]);
              $this->save ();
            }
          }
        }

        $inv->setItem( 49 , ItemFactory::getInstance()->get(426, 0, 1)->setCustomName("구매모드")->setLore([ "해당 아이템을 인벤토리로 옮기면 물품을 구매창이 오픈!\n인벤토리로 가져가보세요." ]) );
        $inv->setItem( 50 , ItemFactory::getInstance()->get(166, 0, 1)->setCustomName("신고모드")->setLore([ "해당 아이템을 인벤토리로 옮기면 물품을 신고창이 오픈!\n인벤토리로 가져가보세요." ]) );
        $inv->setItem( 51 , ItemFactory::getInstance()->get(368, 0, 1)->setCustomName("이전페이지")->setLore([ "해당 아이템을 인벤토리로 옮기면 이전페이지로 이동!.\n인벤토리로 가져가보세요." ]) );
        $inv->setItem( 52 , ItemFactory::getInstance()->get(381, 0, 1)->setCustomName("다음페이지")->setLore([ "해당 아이템을 인벤토리로 옮기면 다음페이지로 이동!.\n인벤토리로 가져가보세요." ]) );
        $inv->setItem( 53 , ItemFactory::getInstance()->get(426, 0, 1)->setCustomName("나가기")->setLore([ "시장 GUI에서 나갑니다.\n인벤토리로 가져가보세요." ]) );
      }
      
      LifeInventoryLib::getInstance ()->send($inv, $player);
    }

    public function onOpOpen($player) {
      $name = $player->getName ();
      $playerPos = $player->getPosition();
      $inv = LifeInventoryLib::getInstance ()->create("HOPPER", new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), '[ MarketManager ] 시장관리',$player);
      $inv->setItem( 1 , ItemFactory::getInstance()->get(386, 0, 1)->setCustomName("신고관리")->setLore([ "신고가 들어온 물품을 관리합니다.\n인벤토리로 가져가보세요." ]) );
      $inv->setItem( 2 , ItemFactory::getInstance()->get(426, 0, 1)->setCustomName("나가기")->setLore([ "시장 도우미 GUI에서 나갑니다.\n인벤토리로 가져가보세요." ]));
      $inv->setItem( 3 , ItemFactory::getInstance()->get(54, 0, 1)->setCustomName("물품관리")->setLore([ "시장 물품을 관리합니다.\n인벤토리로 가져가보세요." ]));
      LifeInventoryLib::getInstance ()->send($inv, $player);
      return true;
    }

    public function WareTaskEvent ($player) {
      $this->getScheduler()->scheduleDelayedTask(new class ($this, $player) extends Task {
        protected $owner;
        public function __construct(MarketManager $owner,Player $player) {
          $this->owner = $owner;
          $this->player = $player;
        }
        public function onRun():void
        {
          $this->owner->WareManagerUI($this->player);
        }
      }, 20);
    }
    public function WareManagerUI(Player $player)
    {
      $name = $player->getName ();

      $Market = $this->pldb [strtolower($name)] ["이용이벤트"];
      $marketname = $this->marketdb ["물품리스트"] [$Market] ["물품이름"];
      $count = $this->marketdb ["물품리스트"] [$Market] ["갯수"];
      $livemoney = $this->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
      $timemoney = $this->marketdb ["물품리스트"] [$Market] ["최소경매가"];
      $livetimemoney = $this->marketdb ["물품리스트"] [$Market] ["현재경매가"];
      $outtime = $this->marketdb ["물품리스트"] [$Market] ["종료시간"];
      $playername = $this->marketdb ["물품리스트"] [$Market] ["등록자이름"];

      $encode = [
        'type' => 'custom_form',
        'title' => '[ MarketManager ] | 신고도우미',
        'content' => [
          [
            'type' => 'dropdown',
            'text' => '이용 방법',
            "options" => ["금액신고","아이템신고"]
          ],
          [
            'type' => 'input',
            'text' => "빈칸에는 신고내용을 적어주세요.\n\n아래 정보의 물품을 신고합니다.\n\n\n§6-----------------------------\n\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n§6만료일 §f=> §6{$outtime}\n\n§6-----------------------------\n"
          ]
        ]
      ];
      $packet = new ModalFormRequestPacket ();
      $packet->formId = 21381;
      $packet->formData = json_encode($encode);
      $player->getNetworkSession()->sendDataPacket($packet);
      return true;
    }

    public function OpWareTaskEvent ($player) {
      $this->getScheduler()->scheduleDelayedTask(new class ($this, $player) extends Task {
        protected $owner;
        public function __construct(MarketManager $owner,Player $player) {
          $this->owner = $owner;
          $this->player = $player;
        }
        public function onRun():void
        {
          $this->owner->OpWareManagerUI($this->player);
        }
      }, 20);
    }
    public function OpWareManagerUI(Player $player)
    {
      $name = $player->getName ();

      $Market = $this->pldb [strtolower($name)] ["이용이벤트"];
      $count = $this->marketdb ["신고물품"] [$Market] ["갯수"];
      $livemoney = $this->marketdb ["신고물품"] [$Market] ["즉시구매가"];
      $timemoney = $this->marketdb ["신고물품"] [$Market] ["최소경매가"];
      $livetimemoney = $this->marketdb ["신고물품"] [$Market] ["현재경매가"];
      $playername = $this->marketdb ["신고물품"] [$Market] ["등록자이름"];
      $wareplayer = $this->marketdb ["신고물품"] [$Market] ["신고자"];
      $xyz = $this->marketdb ["신고물품"] [$Market] ["신고위치"];
      $message = $this->marketdb ["신고물품"] [$Market] ["신고글"];

      $encode = [
        'type' => 'custom_form',
        'title' => '[ MarketManager ] | 신고관리',
        'content' => [
          [
            'type' => 'dropdown',
            'text' => '이용 방법',
            "options" => ["신고체결","신고무효화"]
          ],
          [
            'type' => 'input',
            'text' => "신고체결을 하실 경우 지급할 경고의 정도를 적어주세요.\n\n신고무효화를 하실 경우 YES 를 적어주세요.\n\n아래 정보의 물품의 신고를 관리합니다.\n\n\n§6-----------------------------\n\n§6{$wareplayer} §f님이 §6{$xyz} §6의 문제로 신고했습니다.\n\n§6신고자의 메세지 §f=> §6{$message}\n\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n\n§6-----------------------------\n"
          ]
        ]
      ];
      $packet = new ModalFormRequestPacket ();
      $packet->formId = 21382;
      $packet->formData = json_encode($encode);
      $player->getNetworkSession()->sendDataPacket($packet);
      return true;
    }

    public function MarketWareEvent($player) {
      $tag = "[ MarketManager ] ";
      $name = $player->getName ();
      $playerPos = $player->getPosition();
      $inv = LifeInventoryLib::getInstance ()->create("DOUBLE_CHEST", new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), '[ MarketManager ] 신고관리',$player);
      $arr = [];
      $i = 0;
      if (isset($this->marketdb ["신고물품"])) {
        foreach($this->marketdb ["신고물품"]as $Market => $v){
          if (isset($this->marketdb ["신고물품"] [$Market])) {
            $marketname = $this->marketdb ["신고물품"] [$Market] ["물품이름"];
            $count = $this->marketdb ["신고물품"] [$Market] ["갯수"];
            $livemoney = $this->marketdb ["신고물품"] [$Market] ["즉시구매가"];
            $timemoney = $this->marketdb ["신고물품"] [$Market] ["최소경매가"];
            $livetimemoney = $this->marketdb ["신고물품"] [$Market] ["현재경매가"];
            $playername = $this->marketdb ["신고물품"] [$Market] ["등록자이름"];
            $wareplayer = $this->marketdb ["신고물품"] [$Market] ["신고자"];
            $xyz = $this->marketdb ["신고물품"] [$Market] ["신고위치"];
            $message = $this->marketdb ["신고물품"] [$Market] ["신고글"];
            $item = Item::jsonDeserialize($this->marketdb['물품리스트'][$Market]['nbt']);
            $lore = $item->getLore();
            $lore [] = "§6{$wareplayer} §f님이 §6{$xyz} §f의 문제로 신고했습니다.\n\n§6신고자의 메세지 §f=> §c{$message}\n\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원";
            $item->setLore ($lore);
            $inv->setItem( $i , $item );
            $i++;
          }
        }
      }
      $inv->setItem( 53 , ItemFactory::getInstance()->get(426, 0, 1)->setCustomName("나가기")->setLore([ "시장 GUI에서 나갑니다.\n인벤토리로 가져가보세요." ]) );
      
      LifeInventoryLib::getInstance ()->send($inv, $player);
    }

    public function MarketItemCheckEvent($player) {
      $tag = "[ MarketManager ] ";
      $name = $player->getName ();
      $livetime = (int)date("YmdHis");
      $playerPos = $player->getPosition();
      $inv = LifeInventoryLib::getInstance ()->create("DOUBLE_CHEST", new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), '[ MarketManager ] 시장터관리장',$player);
      $arr = [];
      $i = 0;
      $page = 1;
      unset($this->allmarketdb ["물품리스트"]);
      $this->save ();
      if (isset($this->marketdb ["물품리스트"])){
        foreach($this->marketdb ["물품리스트"] as $MarketItem => $v){
          if ( $i <= 48) {
            $this->allmarketdb ["물품리스트"] [$page] [$i] = $MarketItem;
            $this->save ();
          } else {
            ++$page;
            $pageData = (int)$page-1;
            $getpage = (int)$page*49;
            $iData = $page-$getpage;
            $this->allmarketdb ["물품리스트"] [$page] [$iData] = $MarketItem;
            $this->save ();
          }
          ++$i;
        }
        $playerpage = $this->pldb [strtolower($name)] ["페이지"];
        if (isset($this->allmarketdb ["물품리스트"] [$playerpage])) {
          foreach($this->allmarketdb ["물품리스트"] [$playerpage] as $is => $v){

            $Market = $this->allmarketdb ["물품리스트"] [$playerpage] [$is];
            if (isset($this->marketdb ["물품리스트"] [$Market])) {
              $marketname = $this->marketdb ["물품리스트"] [$Market] ["물품이름"];
              $count = $this->marketdb ["물품리스트"] [$Market] ["갯수"];
              $livemoney = $this->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
              $timemoney = $this->marketdb ["물품리스트"] [$Market] ["최소경매가"];
              $livetimemoney = $this->marketdb ["물품리스트"] [$Market] ["현재경매가"];
              $outtime = $this->marketdb ["물품리스트"] [$Market] ["종료시간"];
              $playername = $this->marketdb ["물품리스트"] [$Market] ["등록자이름"];
              $item = Item::jsonDeserialize($this->marketdb['물품리스트'][$Market]['nbt']);
              $item->setCustomName ($marketname);
              $lore = $item->getLore();
              $lore [] = "\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n§6만료일 §f=> §6{$outtime}\n\n§6인벤토리에 가져오면 구매도우미가 열립니다.";
              $item->setLore ($lore);
              $inv->setItem( $is , $item );
            } else {
              unset($this->allmarketdb ["물품리스트"] [$playerpage] [$is]);
              $this->save ();
            }
          }
        }

        $inv->setItem( 50 , ItemFactory::getInstance()->get(166, 0, 1)->setCustomName("제거모드")->setLore([ "해당 아이템을 인벤토리로 옮기면 물품을 제거창이 오픈!\n인벤토리로 가져가보세요." ]) );
        $inv->setItem( 51 , ItemFactory::getInstance()->get(368, 0, 1)->setCustomName("이전페이지")->setLore([ "해당 아이템을 인벤토리로 옮기면 이전페이지로 이동!\n인벤토리로 가져가보세요." ]) );
        $inv->setItem( 52 , ItemFactory::getInstance()->get(381, 0, 1)->setCustomName("다음페이지")->setLore([ "해당 아이템을 인벤토리로 옮기면 다음페이지로 이동!\n인벤토리로 가져가보세요." ]) );
        $inv->setItem( 53 , ItemFactory::getInstance()->get(426, 0, 1)->setCustomName("나가기")->setLore([ "시장 GUI에서 나갑니다.\n인벤토리로 가져가보세요." ]) );
      }
      LifeInventoryLib::getInstance ()->send($inv, $player);
    }

    public function MarketMyEvent($player) {
      $tag = "[ MarketManager ] ";
      $name = $player->getName ();
      $playerPos = $player->getPosition();
      $inv = LifeInventoryLib::getInstance ()->create("DOUBLE_CHEST", new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), '[ MarketManager ] 나의 등록물품함',$player);
      $arr = [];
      $i = 0;

      if (isset($this->pldb [strtolower($name)] ["등록리스트"])) {
        foreach($this->pldb [strtolower($name)] ["등록리스트"] as $Market => $v){

          if (isset($this->pldb [strtolower($name)] ["등록리스트"] [$Market])) {
            $marketname = $this->pldb [strtolower($name)] ["등록리스트"] [$Market] ["물품이름"];
            $count = $this->pldb [strtolower($name)] ["등록리스트"] [$Market] ["갯수"];
            $livemoney = $this->pldb [strtolower($name)] ["등록리스트"] [$Market] ["즉시구매가"];
            $timemoney = $this->pldb [strtolower($name)] ["등록리스트"] [$Market] ["최소경매가"];
            $livetimemoney = $this->pldb [strtolower($name)] ["등록리스트"] [$Market] ["현재경매가"];
            $outtime = $this->pldb [strtolower($name)] ["등록리스트"] [$Market] ["종료시간"];
            $playername = $this->pldb [strtolower($name)] ["등록리스트"] [$Market] ["등록자이름"];
            $item = Item::jsonDeserialize($this->marketdb['물품리스트'][$Market]['nbt']);
            $item->setCustomName ($marketname);
            $lore = $item->getLore();
            $lore [] = "\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n§6만료일 §f=> §6{$outtime}\n\n§6인벤토리에 가져오면 관리도우미가 열립니다.";
            $item->setLore ($lore);
            $inv->setItem( $i , $item );
            $i++;
          }
        }
      }
      $inv->setItem( 53 , ItemFactory::getInstance()->get(426, 0, 1)->setCustomName("나가기")->setLore([ "내 시장 GUI에서 나갑니다.\n인벤토리로 가져가보세요." ]) );
      LifeInventoryLib::getInstance ()->send($inv, $player);
    }

    public function MyTaskEvent ($player) {
      $this->getScheduler()->scheduleDelayedTask(new class ($this, $player) extends Task {
        protected $owner;
        public function __construct(MarketManager $owner,Player $player) {
          $this->owner = $owner;
          $this->player = $player;
        }
        public function onRun():void
        {
          $this->owner->MyManagerUI($this->player);
        }
      }, 20);
    }
    public function MyManagerUI(Player $player)
    {
      $name = $player->getName ();

      $Market = $this->pldb [strtolower($name)] ["이용이벤트"];
      $count = $this->marketdb ["물품리스트"] [$Market] ["갯수"];
      $livemoney = $this->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
      $timemoney = $this->marketdb ["물품리스트"] [$Market] ["최소경매가"];
      $livetimemoney = $this->marketdb ["물품리스트"] [$Market] ["현재경매가"];
      $playername = $this->marketdb ["물품리스트"] [$Market] ["등록자이름"];
      $time = $this->marketdb ["물품리스트"] [$Market] ["종료시간"];

      $encode = [
        'type' => 'form',
        'title' => '[ MarketManager ]',
        'content' => "아래 정보의 물품을 관리합니다.\n\n§6-----------------------------\n\n§6물품이름 §f=> §6{$Market}\n\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n§6등록만료 §f=> §6{$time}\n\n§6-----------------------------\n",
        'buttons' => [
          [
            'text' => '등록 제거'
          ],
          [
            'text' => '등록기간 연장'
          ]
        ]
      ];
      $packet = new ModalFormRequestPacket ();
      $packet->formId = 21383;
      $packet->formData = json_encode($encode);
      $player->getNetworkSession()->sendDataPacket($packet);
      return true;
    }

    public function RemoveTaskEvent ($player) {
      $this->getScheduler()->scheduleDelayedTask(new class ($this, $player) extends Task {
        protected $owner;
        public function __construct(MarketManager $owner,Player $player) {
          $this->owner = $owner;
          $this->player = $player;
        }
        public function onRun():void
        {
          $this->owner->RemoveManagerUI($this->player);
        }
      }, 20);
    }

    public function RemoveManagerUI(Player $player)
    {
      $name = $player->getName ();

      $Market = $this->pldb [strtolower($name)] ["이용이벤트"];
      $marketname = $this->marketdb ["물품리스트"] [$Market] ["물품이름"];
      $count = $this->marketdb ["물품리스트"] [$Market] ["갯수"];
      $livemoney = $this->marketdb ["물품리스트"] [$Market] ["즉시구매가"];
      $timemoney = $this->marketdb ["물품리스트"] [$Market] ["최소경매가"];
      $livetimemoney = $this->marketdb ["물품리스트"] [$Market] ["현재경매가"];
      $outtime = $this->marketdb ["물품리스트"] [$Market] ["종료시간"];
      $playername = $this->marketdb ["물품리스트"] [$Market] ["등록자이름"];

      $encode = [
        'type' => 'custom_form',
        'title' => '[ MarketManager ] | 제거도우미',
        'content' => [
          [
            'type' => 'dropdown',
            'text' => '이용 방법',
            "options" => ["물품반환제거","물품회수제거"]
          ],
          [
            'type' => 'input',
            'text' => "빈칸에는 YES 를 적어주세요.\n\n아래 정보의 물품을 제거하려고 합니다.\n\n\n§6-----------------------------\n\n§6등록자 §f=> §6{$playername}\n\n§6수량 §f=> §6{$count} §f개\n§6판매가 §f=> §6{$livemoney} §f원\n§6경매가 §f=> §6{$livetimemoney} §f원\n§6만료일 §f=> §6{$outtime}\n\n§6-----------------------------\n"
          ]
        ]
      ];
      $packet = new ModalFormRequestPacket ();
      $packet->formId = 21384;
      $packet->formData = json_encode($encode);
      $player->getNetworkSession()->sendDataPacket($packet);
      return true;
    }

    public function MarketTimeCheck()
    {
      if (isset($this->marketdb ["물품리스트"])) {
        foreach($this->marketdb ["물품리스트"]as $Market => $v){
          if (isset($this->marketdb ["물품리스트"] [$Market])) {
            $marketname = $this->marketdb ["물품리스트"] [$Market] ["물품이름"];
            $count = $this->marketdb ["물품리스트"] [$Market] ["갯수"];
            $livetimemoney = $this->marketdb ["물품리스트"] [$Market] ["현재경매가"];
            $outtime = $this->marketdb ["물품리스트"] [$Market] ["종료시간"];
            $upname = $this->marketdb ["물품리스트"] [$Market] ["등록자이름"];
            $playername = $this->marketdb ["물품리스트"] [$Market] ["경매자"];
            $time = (int)$this->marketdb ["물품리스트"] [$Market] ["등록종료시간"];
            $livetime = (int)date("YmdHis");
            $item = Item::jsonDeserialize($this->marketdb['물품리스트'][$Market]['nbt']);
            if ($livetime > $time) {
              if (MoneyManager::getInstance ()->getMoney ($playername) >= $livetimemoney){
                MoneyManager::getInstance ()->sellMoney ($playername,$livemoney);
                MoneyManager::getInstance ()->addMoney ($upname,$livemoney);
                $this->GiveItem ($playername,$Market,$item->jsonSerialize ());
                if ($this->getServer()->getPlayerExact($playername) != null) {
                  $this->getServer()->getPlayerExact($playername)->sendMessage ( $this->tag() . "경매에 참여했던 물품을 {$livemoney} 원으로 정상적으로 경매 체결 했습니다." );
                }
                if ($this->getServer()->getPlayerExact($upname) != null) {
                  $this->getServer()->getPlayerExact($upname)->sendMessage ( $this->tag() . "시장에 등록한 물품의 경매가 정상적으로 진행되어 {$livemoney} 원을 수령하고 판매 했습니다." );
                }
                unset($this->pldb [strtolower($upname)] ["등록리스트"] [$Market]);
                unset($this->marketdb ["물품리스트"] [$Market]);
                $this->save ();
                return true;
              }
              if (MoneyManager::getInstance ()->getMoney ($playername) < $livetimemoney){
                $this->GiveItem ($upname,$Market,$item->jsonSerialize ());
                if ($this->getServer()->getPlayerExact($playername) != null) {
                  $this->getServer()->getPlayerExact($playername)->sendMessage ( $this->tag() . "경매에 참여했던 물품을 수령하는 과정에서 돈이 부족하여 경매가 체결이 취소됐습니다." );
                }
                if ($this->getServer()->getPlayerExact($upname) != null) {
                  $this->getServer()->getPlayerExact($upname)->sendMessage ( $this->tag() . "시장에 등록한 물품의 경매참여자에 돈이 부족하여 경매와 물품 판매가 취소됐습니다." );
                }
                unset($this->pldb [strtolower($upname)] ["등록리스트"] [$Market]);
                unset($this->marketdb ["물품리스트"] [$Market]);
                $this->save ();
                return true;
              }
            }
          }
        }
      }
    }
    
    public function GiveItem($giveplayer,$couponname,$nbt)
    {
      MailboxAPI::getInstance ()->getInventoryPlayerItem($giveplayer,$couponname,$nbt);
      return true;
    }
    
    public function onDisable():void
    {
      $this->save();
    }
    public function save():void
    {
      $this->player->setAll($this->pldb);
      $this->player->save();
      $this->market->setAll($this->marketdb);
      $this->market->save();
      $this->allmarket->setAll($this->allmarketdb);
      $this->allmarket->save();
      $this->Warning->setAll($this->Warningdb);
      $this->Warning->save();
    }
  }
  class PlayerMarketTask extends Task {
    protected $owner;
    public function __construct(MarketManager $owner) {
      $this->owner = $owner;
    }
    public function onRun():void {
      $this->owner->MarketTimeCheck ();
    }
  }

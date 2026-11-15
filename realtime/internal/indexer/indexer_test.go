package indexer

import (
	"math/big"
	"testing"

	"github.com/ethereum/go-ethereum/common"
	"github.com/ethereum/go-ethereum/core/types"
)

func TestFormatUnits(t *testing.T) {
	cases := []struct {
		value    int64
		decimals int
		want     string
	}{
		{1234, 2, "12.34"}, {100000, 2, "1000"}, {5, 2, "0.05"}, {50, 2, "0.5"}, {0, 2, "0"}, {7, 0, "7"}, {1, 18, "0.000000000000000001"},
	}
	for _, c := range cases {
		if got := FormatUnits(big.NewInt(c.value), c.decimals); got != c.want {
			t.Errorf("FormatUnits(%d, %d) = %q, want %q", c.value, c.decimals, got, c.want)
		}
	}
}

func transferLog(from, to common.Address, value int64) types.Log {
	data := common.LeftPadBytes(big.NewInt(value).Bytes(), 32)
	return types.Log{
		Topics:      []common.Hash{TransferTopic, common.BytesToHash(from.Bytes()), common.BytesToHash(to.Bytes())},
		Data:        data,
		BlockNumber: 42,
		TxHash:      common.HexToHash("0x" + "ab"),
	}
}

func TestParseTransfer(t *testing.T) {
	from := common.HexToAddress("0x1111111111111111111111111111111111111111")
	to := common.HexToAddress("0x2222222222222222222222222222222222222222")
	got, ok := ParseTransfer(transferLog(from, to, 1234))
	if !ok || got.From != from || got.To != to || got.Value.Int64() != 1234 || got.Block != 42 {
		t.Fatalf("unexpected transfer %+v ok=%v", got, ok)
	}
	removed := transferLog(from, to, 1)
	removed.Removed = true
	if _, ok := ParseTransfer(removed); ok {
		t.Error("removed log (reorg) must be ignored")
	}
	other := transferLog(from, to, 1)
	other.Topics[0] = common.HexToHash("0x01")
	if _, ok := ParseTransfer(other); ok {
		t.Error("non-Transfer event must be ignored")
	}
	short := transferLog(from, to, 1)
	short.Topics = short.Topics[:2]
	if _, ok := ParseTransfer(short); ok {
		t.Error("malformed Transfer (missing indexed address) must be ignored")
	}
	if TransferTopic.Hex() != "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef" {
		t.Error("unexpected Transfer topic")
	}
}

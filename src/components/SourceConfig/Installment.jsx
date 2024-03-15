import Toggle from 'react-toggle';

export default function Installment({
  uid,
  name,
  description,
  advanced = true,
  active = false,
  index,
  list,
  setList,
}) {
  function setActive(event) {
    const newList = list;
    newList[index].active = event.target.checked;
    setList(newList);
  }

  return (
    <div className="installment">
      <div className="installment-desc">
        <div>
          <p>{name}</p>
          {advanced && <p className="advanced-text">Regla avanzada</p>}
        </div>
        <p className="small">{description}</p>
      </div>
      <Toggle defaultChecked={active} icons={false} onChange={setActive} />
    </div>
  );
}
